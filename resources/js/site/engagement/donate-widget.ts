/**
 * Port of components/engagement/DonateWidgetClient.tsx (+ CopyButton.tsx for the bank panel).
 * Fully controlled like the reference: every state change re-renders the template and morphs it in.
 * The bank-transfer panel is server-rendered (Blade) and passed through untouched.
 */
import type { Frequency, OnlineProvider } from "@/lib/payments/schemas";
import {
  PublicSubmit,
  attrs,
  cn,
  errorMessageKey,
  esc,
  formatMoney,
  icon,
  morph,
  mountTurnstile,
  translations,
} from "./core";
import {
  checkbox,
  errorSummary,
  field,
  honeypot,
  inputClass,
  lightScope,
  privacyConsentLabel,
  turnstileSlot,
} from "./ui";

type Method = OnlineProvider | "BANK";

/** amountLimits of resources/js/lib/payments/schemas.ts (the Zod schema itself loads on submit). */
const amountLimits = {
  EUR: { min: 2, max: 10_000 },
  NPR: { min: 10, max: 500_000 },
} as const;
export type NepalQr = { url: string; merchantId?: string };
type ProviderStatus = Record<OnlineProvider, { available: boolean; testMode: boolean }>;

export type DonateWidgetProps = {
  uid: string;
  locale: string;
  source?: string;
  className?: string;
  projectId?: string;
  campaignId?: string;
  presets: { EUR: number[]; EUR_MONTHLY: number[]; NPR: number[] };
  defaultAmount: number;
  impactNotes: { amount: number; text: string }[];
  providers: ProviderStatus;
  nepalQr: { ESEWA: NepalQr | null; KHALTI: NepalQr | null };
  projects: { id: string; title: string }[];
  privacyHref: string;
  turnstileSiteKey?: string;
};

const METHOD_ORDER: Method[] = ["STRIPE", "PAYPAL", "ESEWA", "KHALTI", "BANK"];
const methodIcon = {
  STRIPE: "CreditCard",
  PAYPAL: "Wallet",
  ESEWA: "Smartphone",
  KHALTI: "Smartphone",
  BANK: "Landmark",
} as const;
const methodKey: Record<Method, string> = {
  STRIPE: "methodCard",
  PAYPAL: "methodPaypal",
  ESEWA: "methodEsewa",
  KHALTI: "methodKhalti",
  BANK: "methodBank",
};

const chip =
  "relative flex min-h-14 cursor-pointer items-center justify-center rounded-[10px] border-[1.5px] border-border-input bg-white px-3 text-lg font-semibold text-fg transition-colors " +
  "has-[:checked]:border-sindoor has-[:checked]:bg-sindoor-50 has-[:checked]:text-sindoor-dark " +
  "has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus";

export function mountDonateWidget(initialRoot: Element, props: DonateWidgetProps) {
  const t = translations("donate");
  const tf = translations("forms");
  const { uid, providers, nepalQr, presets, impactNotes, locale } = props;
  const spam = new PublicSubmit();

  // The bank panel is server-rendered: keep its HTML and reuse it in every render.
  const bankSlot = initialRoot.querySelector("[data-bank-panel]");
  const bankPanelHtml = bankSlot ? bankSlot.innerHTML : "";

  const isUsable = (m: Method, freq: Frequency): boolean => {
    if (m === "BANK") return true;
    if (freq === "monthly") return m === "STRIPE" && providers.STRIPE.available;
    if (m === "ESEWA" || m === "KHALTI") return providers[m].available || !!nepalQr[m];
    return providers[m].available;
  };
  const firstUsable = (freq: Frequency): Method => METHOD_ORDER.find((m) => isUsable(m, freq)) ?? "BANK";
  // A provider that is not set up is left out altogether (no "coming soon" tiles); bank transfer is
  // always offered. With bank transfer alone there is nothing to choose: the widget is just the bank
  // panel, without the card frame around a form. Keep in sync with Components.php.
  const offered = METHOD_ORDER.filter((m) => m === "BANK" || isUsable(m, "once"));
  const bankOnly = offered.length === 1;
  const providerNames = { STRIPE: "Stripe", PAYPAL: "PayPal", ESEWA: "eSewa", KHALTI: "Khalti" } as const;
  const activeProviders = (Object.keys(providerNames) as OnlineProvider[])
    .filter((m) => providers[m].available)
    .map((m) => providerNames[m]);

  let root = initialRoot;
  let frequency: Frequency = "once";
  let method: Method = firstUsable("once");
  let selected: number | "custom" = presets.EUR.includes(props.defaultAmount)
    ? props.defaultAmount
    : (presets.EUR[1] ?? presets.EUR[0] ?? 25);
  let custom = "";
  const details = {
    donorName: "",
    donorEmail: "",
    isAnonymous: false,
    newsletter: false,
    consent: false,
    projectId: props.projectId ?? "",
  };
  let errors: Record<string, string> = {};
  let formError: string | undefined;
  let pending = false;
  let redirecting = false;
  let focusSummary = false;

  const currency = () => (method === "ESEWA" || method === "KHALTI" ? "NPR" : "EUR");
  const amountOf = () => (selected === "custom" ? Number(custom.replace(",", ".")) : selected);
  const amountValid = () => {
    const a = amountOf();
    return Number.isFinite(a) && a > 0;
  };
  const fmt = (major: number, cur = currency()) => formatMoney(Math.round(major * 100), cur, locale);

  const impact = () => {
    const amount = amountOf();
    if (currency() !== "EUR" || !amountValid()) return t("impactGeneric");
    const sorted = [...impactNotes].sort((a, b) => b.amount - a.amount);
    return sorted.find((n) => n.amount <= amount)?.text ?? t("impactGeneric");
  };

  const errText = (key: string | undefined) => {
    if (!key) return undefined;
    const limits = amountLimits[currency()];
    if (key === "amountOutOfRange")
      return tf("errors.amountOutOfRange", { min: fmt(limits.min), max: fmt(limits.max) });
    return tf.has(`errors.${key}`) ? tf(`errors.${key}`) : tf("errors.invalid");
  };

  function changeFrequency(freq: Frequency) {
    frequency = freq;
    if (!isUsable(method, freq)) method = firstUsable(freq);
    const list = freq === "monthly" ? presets.EUR_MONTHLY : presets.EUR;
    selected = list[1] ?? list[0]!;
    custom = "";
  }

  function changeMethod(m: Method) {
    const nextCurrency = m === "ESEWA" || m === "KHALTI" ? "NPR" : "EUR";
    if (nextCurrency !== currency()) {
      const list =
        nextCurrency === "NPR" ? presets.NPR : frequency === "monthly" ? presets.EUR_MONTHLY : presets.EUR;
      selected = list[1] ?? list[0]!;
      custom = "";
    }
    method = m;
    errors = {};
    formError = undefined;
  }

  const render = (): string => {
    const cur = currency();
    const chipAmounts =
      cur === "NPR" ? presets.NPR : frequency === "monthly" ? presets.EUR_MONTHLY : presets.EUR;
    const online = method !== "BANK" && providers[method].available;
    const qrOnly =
      (method === "ESEWA" || method === "KHALTI") && !providers[method].available && !!nepalQr[method];
    const testMode = method !== "BANK" && providers[method].available && providers[method].testMode;
    const methodLabel = t(methodKey[method]);
    const limits = amountLimits[cur];
    const amount = amountOf();
    const valid = amountValid();
    const errorItems = Object.entries(errors).map(([k, v]) => ({
      fieldId: k === "amount" ? `${uid}-custom` : `${uid}-${k}`,
      message: errText(v) ?? v,
    }));

    let html = `<div${attrs({
      class: cn(
        lightScope,
        bankOnly
          ? "relative"
          : "relative overflow-hidden rounded-[28px] border border-line bg-[#FFFDF9] shadow-[0_24px_60px_-28px_rgba(16,31,51,0.35)]",
        props.className,
      ),
      "data-engagement": "DonateWidget",
    })}>`;
    if (testMode) {
      html +=
        `<p class="flex items-center justify-center gap-2 bg-warning-bg px-4 py-2 text-sm font-semibold text-warning">` +
        icon("FlaskConical", "size-4") +
        `${esc(t("testMode"))}</p>`;
    }
    html += `<form${attrs({ novalidate: true, "aria-label": t("title"), class: bankOnly ? "flex flex-col gap-7" : "flex flex-col gap-7 p-5 sm:p-8" })}>`;
    if (errorItems.length || formError) {
      html += errorSummary({
        title: tf("errorSummary"),
        items: formError ? [] : errorItems,
        message: formError,
      }).replace("<div", '<div data-summary=""');
    }

    // Step 1 — frequency. Monthly giving needs a card: without Stripe there is nothing to choose, so
    // the step is left out (no disabled "monthly" option). Keep in sync with Components.php.
    if (providers.STRIPE.available) {
      html += `<fieldset><legend class="mb-3 text-[15px] font-semibold text-heading">${esc(t("frequencyLegend"))}</legend>`;
      html += `<div class="grid grid-cols-2 gap-1 rounded-full bg-cream p-1">`;
      for (const f of ["once", "monthly"] as const) {
        html +=
          `<label class="${esc(
            cn(
              "flex min-h-11 cursor-pointer items-center justify-center gap-1.5 rounded-full px-3 text-[15px] font-semibold whitespace-nowrap text-fg-muted transition-colors",
              "has-[:checked]:bg-white has-[:checked]:text-heading has-[:checked]:shadow-sm",
              "has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus",
            ),
          )}">` +
          `<input${attrs({
            type: "radio",
            name: `${uid}-frequency`,
            value: f,
            checked: frequency === f,
            class: "sr-only",
            "data-role": "frequency",
          })}/>` +
          esc(t(f)) +
          (f === "monthly"
            ? `<span class="hidden rounded-full bg-sage/15 px-2 py-0.5 text-xs font-medium text-sage-700 min-[420px]:inline">${esc(t("recommended"))}</span>`
            : "") +
          `</label>`;
      }
      html += `</div>`;
      if (frequency === "monthly") {
        html += `<p class="mt-2 text-sm text-fg-muted">${esc(t("monthlyCardOnly"))}</p>`;
      }
      html += `</fieldset>`;
    }

    // Step 1 — amount (hidden for the bank panel)
    if (method !== "BANK" && !qrOnly) {
      html +=
        `<fieldset><legend class="mb-3 text-[15px] font-semibold text-heading">${esc(t("amountLegend"))} ` +
        `<span class="font-normal text-fg-muted">(${esc(cur)})</span></legend>` +
        `<div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">`;
      for (const a of chipAmounts) {
        html +=
          `<label class="${esc(chip)}">` +
          `<input${attrs({
            type: "radio",
            name: `${uid}-amount`,
            value: a,
            checked: selected === a,
            class: "sr-only",
            "data-role": "amount",
          })}/>` +
          (selected === a ? icon("Check", "absolute top-1.5 right-1.5 size-4") : "") +
          esc(fmt(a)) +
          (frequency === "monthly" && cur === "EUR"
            ? `<span class="ml-0.5 text-sm font-normal">${esc(t("perMonth"))}</span>`
            : "") +
          `</label>`;
      }
      html += `</div><div class="mt-3">`;
      html += field({
        id: `${uid}-custom`,
        label: t("customAmountLabel", { currency: cur }),
        error: errText(errors.amount),
        control: ({ id, describedBy, invalid }) =>
          `<div class="relative"><span aria-hidden="true" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 font-semibold text-fg-muted">${
            cur === "EUR" ? "€" : "Rs"
          }</span>` +
          `<input${attrs({
            id,
            type: "text",
            inputmode: "decimal",
            autocomplete: "off",
            "aria-describedby": describedBy,
            "aria-invalid": invalid ? "true" : undefined,
            value: custom,
            placeholder: `${limits.min}–${limits.max}`,
            class: cn(inputClass, cur === "EUR" ? "pl-9" : "pl-11"),
            "data-role": "custom",
          })}/></div>`,
      });
      html +=
        `</div><p aria-live="polite" class="mt-3 flex items-start gap-2 rounded-[14px] bg-sage-50 p-3.5 text-[15px] leading-snug text-sage-700">` +
        icon("Heart", "mt-0.5 size-4 shrink-0 text-sage") +
        `<span>${valid ? `<strong class="font-semibold">${esc(fmt(amount))} · </strong>` : ""}${esc(impact())}</span></p>` +
        `</fieldset>`;
    }

    // Step 2 — method
    const showMethods = !bankOnly;
    if (showMethods) {
      html +=
        `<fieldset><legend class="mb-3 text-[15px] font-semibold text-heading">${esc(t("methodLegend"))}</legend>` +
        `<div class="grid gap-2.5 sm:grid-cols-2">`;
    }
    for (const m of showMethods ? offered : []) {
      const usable = isUsable(m, frequency);
      const hint =
        m === "STRIPE"
          ? t("methodCardHint")
          : m === "BANK"
            ? t("methodBankHint")
            : m === "ESEWA" || m === "KHALTI"
              ? t("nprHint")
              : undefined;
      // Offered methods can only be unusable for a monthly gift (card only).
      const unavailableReason = !usable ? t("monthlyCardOnly") : undefined;
      html +=
        `<label${attrs({
          "data-method": m,
          class: cn(
            "flex min-h-16 items-center gap-3 rounded-[14px] border-[1.5px] border-border-input px-4 py-3 transition-colors",
            "has-[:checked]:border-sindoor has-[:checked]:bg-sindoor-50",
            "has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus",
            usable ? "cursor-pointer bg-white" : "cursor-not-allowed border-dashed bg-paper opacity-70",
            m === "BANK" && "sm:col-span-2",
          ),
        })}>` +
        `<input${attrs({
          type: "radio",
          name: `${uid}-method`,
          value: m,
          checked: method === m,
          disabled: !usable,
          class: "size-4 shrink-0 accent-sindoor",
          "data-role": "method",
        })}/>` +
        icon(methodIcon[m], "size-5 shrink-0 text-himalaya") +
        `<span class="flex flex-1 flex-col"><span class="text-[15px] font-semibold text-fg">${esc(t(methodKey[m]))}</span>` +
        (hint && usable ? `<span class="text-sm text-fg-muted">${esc(hint)}</span>` : "") +
        `</span>` +
        (unavailableReason
          ? `<span class="rounded-full bg-warning-bg px-2 py-0.5 text-xs font-semibold text-warning">${esc(unavailableReason)}</span>`
          : "") +
        `</label>`;
    }
    if (showMethods) html += `</div></fieldset>`;

    // Step 3 — method-specific
    if (method === "BANK") {
      html += `<div data-bank-panel="">${bankPanelHtml}</div>`;
    } else if (qrOnly && (method === "ESEWA" || method === "KHALTI")) {
      const qr = nepalQr[method]!;
      const provider = method === "ESEWA" ? "eSewa" : "Khalti";
      html +=
        `<div class="flex flex-col items-center gap-3 rounded-[20px] border border-line bg-white p-5 text-center">` +
        `<p class="font-serif text-lg font-semibold text-heading">${esc(t("nepalQrTitle", { provider }))}</p>` +
        `<img${attrs({
          src: qr.url,
          alt: t("nepalQrAlt", { provider }),
          width: 220,
          height: 220,
          class: "rounded-[10px] border border-line",
        })}/>` +
        (qr.merchantId
          ? `<p class="text-[15px]">${esc(t("merchantId"))}: <span class="font-mono font-semibold">${esc(qr.merchantId)}</span></p>`
          : "") +
        `<p lang="ne" class="text-sm text-fg-muted">${esc(t("namaste"))}</p></div>`;
    } else if (online) {
      html += `<fieldset class="flex flex-col gap-4"><legend class="mb-3 text-[15px] font-semibold text-heading">${esc(t("detailsLegend"))}</legend>`;
      if (!props.projectId && props.projects.length) {
        html += field({
          id: `${uid}-projectId`,
          label: t("destination"),
          optionalLabel: tf("optional"),
          control: ({ id }) =>
            `<select${attrs({ id, class: cn(inputClass, "appearance-auto"), "data-role": "projectId" })}>` +
            `<option${attrs({ value: "", selected: details.projectId === "" })}>${esc(t("whereNeeded"))}</option>` +
            props.projects
              .map(
                (p) =>
                  `<option${attrs({ value: p.id, selected: details.projectId === p.id })}>${esc(p.title)}</option>`,
              )
              .join("") +
            `</select>`,
        });
      }
      html += `<div class="grid gap-4 sm:grid-cols-2">`;
      html += field({
        id: `${uid}-donorName`,
        label: t("donorName"),
        optionalLabel: tf("optional"),
        error: errText(errors.donorName),
        control: ({ id, describedBy, invalid }) =>
          `<input${attrs({
            id,
            autocomplete: "name",
            "aria-describedby": describedBy,
            "aria-invalid": invalid ? "true" : undefined,
            value: details.donorName,
            class: inputClass,
            "data-role": "donorName",
          })}/>`,
      });
      html += field({
        id: `${uid}-donorEmail`,
        label: t("donorEmail"),
        optionalLabel: details.newsletter ? undefined : tf("optional"),
        error: errText(errors.donorEmail),
        control: ({ id, describedBy, invalid }) =>
          `<input${attrs({
            id,
            type: "email",
            inputmode: "email",
            autocomplete: "email",
            "aria-describedby": describedBy,
            "aria-invalid": invalid ? "true" : undefined,
            value: details.donorEmail,
            class: inputClass,
            "data-role": "donorEmail",
          })}/>`,
      });
      html += `</div>`;
      html += checkbox({
        id: `${uid}-anon`,
        labelHtml: esc(t("anonymous")),
        checked: details.isAnonymous,
      }).replace('type="checkbox"', 'type="checkbox" data-role="isAnonymous"');
      html += checkbox({
        id: `${uid}-nl`,
        labelHtml: esc(t("newsletterOptIn")),
        checked: details.newsletter,
      }).replace('type="checkbox"', 'type="checkbox" data-role="newsletter"');
      html += checkbox({
        id: `${uid}-consent`,
        ariaRequired: true,
        error: errText(errors.consent),
        checked: details.consent,
        labelHtml: privacyConsentLabel({
          before: tf("privacyConsentBefore"),
          link: tf("privacyLink"),
          after: tf("privacyConsentAfter"),
          href: props.privacyHref,
        }),
      }).replace('type="checkbox"', 'type="checkbox" data-role="consent"');
      html += honeypot(tf("honeypotLabel"), spam.honeypot);
      html += turnstileSlot(props.turnstileSiteKey);
      html +=
        `<button${attrs({
          type: "submit",
          disabled: pending || redirecting,
          class:
            "inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-full bg-sindoor px-6 text-lg font-semibold text-white shadow-[0_10px_24px_-10px_rgba(181,69,47,0.7)] transition-colors hover:bg-sindoor-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-wait disabled:opacity-75",
        })}>` +
        icon("Heart", "size-5") +
        esc(
          pending || redirecting
            ? t("redirecting")
            : t(frequency === "monthly" ? "donateButtonMonthly" : "donateButton", {
                amount: valid ? fmt(amount) : "—",
                method: methodLabel,
              }),
        ) +
        `</button>` +
        `<p aria-live="polite" class="sr-only">${redirecting ? esc(t("redirecting")) : ""}</p>` +
        `</fieldset>`;
    }

    // Only name the providers that are really set up; with none there is no online payment to describe.
    if (activeProviders.length) {
      const names = [...activeProviders];
      const last = names.pop()!;
      const list = names.length ? names.join(", ") + " " + t("listOr") + " " + last : last;
      html +=
        `<p class="flex items-start gap-2 border-t border-line pt-4 text-sm text-fg-muted">` +
        icon("Lock", "mt-0.5 size-4 shrink-0 text-sage") +
        `${esc(t("trustFooter", { providers: list }))}</p>`;
    }
    html += `</form></div>`;
    return html;
  };

  const update = () => {
    root = morph(root, render());
    mountTurnstile(root, props.turnstileSiteKey, (token) => (spam.turnstileToken = token));
    if (focusSummary) {
      focusSummary = false;
      requestAnimationFrame(() => root.querySelector<HTMLElement>("[data-summary]")?.focus());
    }
  };

  // Set synchronously, before the first await: repeated submit events must start one donation.
  let submitting = false;
  async function onSubmit(e: Event) {
    e.preventDefault();
    if (method === "BANK" || pending || redirecting || submitting) return;
    submitting = true;
    try {
      await submitOnce();
    } finally {
      submitting = false;
    }
  }
  async function submitOnce() {
    formError = undefined;
    const valid = amountValid();
    const payload = {
      provider: method,
      amount: valid ? amountOf() : NaN,
      frequency,
      projectId: details.projectId || undefined,
      campaignId: props.campaignId,
      donorName: details.donorName,
      donorEmail: details.donorEmail,
      isAnonymous: details.isAnonymous,
      newsletter: details.newsletter,
      consent: details.consent,
    };
    const { donationInputSchema } = await import("@/lib/payments/schemas");
    const parsed = donationInputSchema.safeParse(payload);
    if (!parsed.success) {
      const next: Record<string, string> = {};
      for (const issue of parsed.error.issues) {
        const k = String(issue.path[0] ?? "form");
        if (!next[k]) next[k] = issue.message;
      }
      errors = next;
      focusSummary = true;
      update();
      return;
    }
    errors = {};
    const res = await spam.submit<{
      kind: "redirect" | "form";
      url?: string;
      action?: string;
      fields?: Record<string, string>;
    }>("/api/donations", { ...payload, locale, source: props.source }, (p) => {
      pending = p;
      update();
    });
    if (!res.ok) {
      if (res.error.kind === "fields") {
        const next: Record<string, string> = {};
        for (const [k, v] of Object.entries(res.error.fieldErrors)) if (v?.[0]) next[k] = v[0];
        errors = next;
      } else {
        formError =
          res.error.code === "PROVIDER_ERROR" ? t("providerError") : tf(errorMessageKey(res.error.code));
      }
      focusSummary = true;
      update();
      return;
    }
    redirecting = true;
    update();
    if (res.data.kind === "redirect" && res.data.url) {
      window.location.assign(res.data.url);
    } else if (res.data.kind === "form" && res.data.action && res.data.fields) {
      const form = document.createElement("form");
      form.method = "POST";
      form.action = res.data.action;
      for (const [name, value] of Object.entries(res.data.fields)) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = value;
        form.appendChild(input);
      }
      document.body.appendChild(form);
      form.submit();
    }
  }

  const container = root.parentElement ?? document.body;
  const inside = (e: Event) => e.target instanceof Node && root.contains(e.target);
  container.addEventListener("submit", (e) => inside(e) && void onSubmit(e));
  container.addEventListener("focusin", (e) => {
    if (!inside(e)) return;
    spam.markStart();
    const el = e.target as HTMLElement;
    if (el.dataset.role === "custom" && custom) {
      selected = "custom";
      update();
    }
  });
  container.addEventListener("input", (e) => {
    if (!inside(e)) return;
    const el = e.target as HTMLInputElement;
    switch (el.dataset.role ?? el.name) {
      case "custom":
        custom = el.value.replace(/[^\d.,]/g, "").slice(0, 9);
        selected = "custom";
        break;
      case "donorName":
        details.donorName = el.value;
        break;
      case "donorEmail":
        details.donorEmail = el.value;
        break;
      case "hp_extra":
        spam.honeypot = el.value;
        return;
      default:
        return;
    }
    update();
  });
  container.addEventListener("change", (e) => {
    if (!inside(e)) return;
    const el = e.target as HTMLInputElement;
    switch (el.dataset.role) {
      case "frequency":
        changeFrequency(el.value as Frequency);
        break;
      case "amount":
        selected = Number(el.value);
        custom = "";
        break;
      case "method":
        changeMethod(el.value as Method);
        break;
      case "projectId":
        details.projectId = el.value;
        break;
      case "isAnonymous":
        details.isAnonymous = el.checked;
        break;
      case "newsletter":
        details.newsletter = el.checked;
        break;
      case "consent":
        details.consent = el.checked;
        break;
      default:
        return;
    }
    update();
  });

  // Deep links from teasers: ?amount=25 (or ?importo=25) and ?method=bank, read once after mount.
  const q = new URLSearchParams(window.location.search);
  const a = Number(q.get("amount") ?? q.get("importo"));
  if (Number.isFinite(a) && a > 0) {
    if (presets.EUR.includes(a)) selected = a;
    else {
      selected = "custom";
      custom = String(a);
    }
  }
  if (q.get("method") === "bank") method = "BANK";

  spam.markStart();
  update();
}

/** Copy-to-clipboard buttons in the (server-rendered) bank panel, with an aria-live confirmation. */
export function enhanceCopyButtons(scope: ParentNode = document) {
  for (const button of scope.querySelectorAll<HTMLButtonElement>("button[data-copy-value]")) {
    if (button.dataset.copyReady) continue;
    button.dataset.copyReady = "1";
    const label = button.dataset.copyLabel ?? "";
    const copied = button.dataset.copiedLabel ?? "";
    const live = button.nextElementSibling;
    const paint = (isCopied: boolean) => {
      button.innerHTML = icon(isCopied ? "Check" : "Copy", "size-4") + esc(isCopied ? copied : label);
      if (live) live.textContent = isCopied ? copied : "";
    };
    button.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.copyValue ?? "");
        paint(true);
        setTimeout(() => paint(false), 2500);
      } catch {
        /* clipboard blocked: the value is still selectable on screen */
      }
    });
  }
}
