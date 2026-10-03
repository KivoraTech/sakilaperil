/**
 * Port of components/engagement/NewsletterFormClient.tsx (inline / stacked, light / dark) and
 * UnsubscribeForm.tsx. React Hook Form default mode: validate on submit, then on every change.
 */
import type { z } from "zod";
import {
  PublicSubmit,
  attrs,
  cn,
  errorMessageKey,
  esc,
  icon,
  morph,
  mountTurnstile,
  translations,
} from "./core";
import { checkbox, honeypot, inputClass, submitButton, successPanel, turnstileSlot } from "./ui";

export type NewsletterFormProps = {
  /** Stable id prefix (React's useId() in the reference). */
  uid: string;
  locale: string;
  source?: string;
  variant?: "inline" | "stacked";
  tone?: "light" | "dark";
  providerConfigured: boolean;
  privacyHref: string;
  successMessage?: string;
  turnstileSiteKey?: string;
  className?: string;
};

export function mountNewsletterForm(initialRoot: Element, props: NewsletterFormProps) {
  const t = translations("newsletter");
  const tf = translations("forms");
  const id = props.uid;
  const variant = props.variant ?? "inline";
  const dark = (props.tone ?? "light") === "dark";
  const spam = new PublicSubmit();

  let root = initialRoot;
  let done = false;
  let error: string | undefined;
  let errors: Record<string, string> = {};
  let submitted = false;
  let pending = false;
  let focusSuccess = false;

  const values = () => {
    const email = root.querySelector<HTMLInputElement>(`#${CSS.escape(`${id}-email`)}`);
    const consent = root.querySelector<HTMLInputElement>(`#${CSS.escape(`${id}-consent`)}`);
    return { email: email?.value ?? "", consent: !!consent?.checked };
  };
  let schema: z.ZodType | null = null;
  const ensureSchema = async () => {
    schema ??= (await import("@/lib/forms/schemas")).newsletterSchema as z.ZodType;
    return schema;
  };
  const validate = () => {
    if (!schema) return true;
    const r = schema.safeParse(values());
    const next: Record<string, string> = {};
    if (!r.success) {
      for (const issue of r.error.issues) {
        const k = String(issue.path[0] ?? "");
        if (k && !next[k]) next[k] = issue.message;
      }
    }
    errors = next;
    return r.success;
  };
  const fieldError = (name: "email" | "consent") => {
    const key = errors[name];
    return key ? (tf.has(`errors.${key}`) ? tf(`errors.${key}`) : tf("errors.invalid")) : undefined;
  };

  const render = (): string => {
    if (done) {
      return (
        `<p${attrs({
          tabindex: "-1",
          role: "status",
          "data-testid": "newsletter-success",
          class: cn(
            "flex items-start gap-3 rounded-[14px] p-4 text-base focus:outline-2 focus:outline-offset-2",
            dark
              ? "bg-white/10 text-white focus:outline-[#F6C77A]"
              : "bg-success-bg text-success focus:outline-focus",
            props.className,
          ),
        })}>` +
        icon("CheckCircle2", "mt-0.5 size-5 shrink-0") +
        `<span><strong class="block">${esc(t("successTitle"))}</strong>${esc(
          props.successMessage || (props.providerConfigured ? t("success") : t("successLocal")),
        )}</span></p>`
      );
    }
    const emailError = fieldError("email");
    const focusRing = dark ? "focus-visible:outline-[#F6C77A]" : "focus-visible:outline-focus";
    return (
      `<form${attrs({
        novalidate: true,
        // The footer form is on every page: its own name keeps two newsletter forms on one page distinct.
        "aria-label": props.source === "footer" ? t("footerLabel") : t("pageTitle"),
        class: cn("relative flex flex-col gap-3", props.className),
        "data-engagement": "newsletter-form",
      })}>` +
      `<label${attrs({ for: `${id}-email`, class: cn("text-[15px] font-medium", dark ? "text-white" : "text-fg") })}>${esc(t("emailLabel"))}</label>` +
      `<div class="${esc(cn("flex gap-2", variant === "stacked" ? "flex-col" : "flex-col sm:flex-row"))}">` +
      `<input${attrs({
        id: `${id}-email`,
        type: "email",
        autocomplete: "email",
        inputmode: "email",
        placeholder: t("placeholder"),
        "aria-invalid": emailError ? "true" : undefined,
        "aria-describedby": emailError ? `${id}-email-error` : `${id}-note`,
        class: cn(inputClass, "sm:flex-1", focusRing),
        name: "email",
      })}/>` +
      `<button${attrs({
        type: "submit",
        disabled: pending,
        class: cn(
          "inline-flex min-h-12 items-center justify-center rounded-full px-6 font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-70",
          dark ? "bg-marigold text-deep hover:bg-[#F6C77A]" : "bg-sindoor text-white hover:bg-sindoor-dark",
          focusRing,
        ),
      })}>${esc(pending ? tf("sending") : t("subscribe"))}</button>` +
      `</div>` +
      (emailError
        ? `<p${attrs({ id: `${id}-email-error`, class: cn("text-sm font-medium", dark ? "text-[#FFD2C8]" : "text-error") })}>${esc(emailError)}</p>`
        : "") +
      `<div${attrs({ class: dark ? "[&_label]:text-white/90 [&_p]:text-[#FFD2C8]" : undefined })}>` +
      checkbox({
        id: `${id}-consent`,
        error: fieldError("consent"),
        name: "consent",
        labelHtml:
          `${esc(t("consent"))} ` +
          `<a${attrs({
            href: props.privacyHref,
            target: "_blank",
            rel: "noopener",
            class: cn("underline underline-offset-2", dark ? "text-white" : "text-himalaya"),
          })}>${esc(tf("privacyLink"))}</a>`,
      }) +
      `</div>` +
      `<p${attrs({ id: `${id}-note`, class: cn("text-sm", dark ? "text-white/75" : "text-fg-muted") })}>${esc(t("note"))}</p>` +
      (error
        ? `<p role="alert" class="${esc(cn("text-sm font-medium", dark ? "text-[#FFD2C8]" : "text-error"))}">${esc(error)}</p>`
        : "") +
      honeypot(tf("honeypotLabel"), spam.honeypot) +
      turnstileSlot(props.turnstileSiteKey) +
      `</form>`
    );
  };

  const update = () => {
    root = morph(root, render(), { uncontrolled: true });
    mountTurnstile(root, props.turnstileSiteKey, (token) => (spam.turnstileToken = token));
    if (focusSuccess && done) {
      focusSuccess = false;
      (root as HTMLElement).focus();
    }
  };

  // Set synchronously, before the first await: repeated submit events must send one request.
  let submitting = false;
  const onSubmit = async (event: Event) => {
    event.preventDefault();
    if (pending || submitting) return;
    submitting = true;
    try {
      await submitOnce();
    } finally {
      submitting = false;
    }
  };
  const submitOnce = async () => {
    submitted = true;
    await ensureSchema();
    if (!validate()) {
      update();
      // Move focus to the first field to correct (the other forms focus their error summary).
      const first = errors.email ? `${id}-email` : errors.consent ? `${id}-consent` : null;
      if (first) root.querySelector<HTMLElement>(`#${CSS.escape(first)}`)?.focus();
      return;
    }
    error = undefined;
    const res = await spam.submit(
      "/api/newsletter/subscribe",
      { ...values(), locale: props.locale, source: props.source },
      (p) => {
        pending = p;
        update();
      },
    );
    if (res.ok) {
      done = true;
      focusSuccess = true;
    } else {
      error = tf(res.error.kind === "fields" ? "errorSummary" : errorMessageKey(res.error.code));
    }
    update();
  };

  const container = root.parentElement ?? document.body;
  const inside = (e: Event) => e.target instanceof Node && root.contains(e.target);
  container.addEventListener("submit", (e) => inside(e) && void onSubmit(e));
  container.addEventListener("focusin", (e) => {
    if (!inside(e)) return;
    spam.markStart();
    void ensureSchema();
  });
  const onEdit = (e: Event) => {
    if (!inside(e) || done) return;
    const target = e.target as HTMLInputElement;
    if (target.name === "hp_extra") spam.honeypot = target.value;
    else if (submitted) {
      validate();
      update();
    }
  };
  container.addEventListener("input", onEdit);
  container.addEventListener("change", onEdit);

  spam.markStart();
  update();
}

/** Explicit POST confirmation, so mail link scanners (GET) never unsubscribe anyone. */
export function mountUnsubscribeForm(initialRoot: Element, props: { token: string }) {
  const t = translations("newsletter");
  const tf = translations("forms");
  let root = initialRoot;
  let state: "idle" | "pending" | "done" | "error" = "idle";

  const render = () =>
    state === "done"
      ? successPanel(t("unsubscribeTitle"), t("unsubscribed"))
      : `<form class="flex flex-col items-start gap-5" data-engagement="unsubscribe-form">` +
        `<p class="text-lead text-fg">${esc(t("unsubscribeBody"))}</p>` +
        (state === "error" ? `<p role="alert" class="font-medium text-error">${esc(t("invalid"))}</p>` : "") +
        submitButton({
          pending: state === "pending",
          label: t("unsubscribeButton"),
          pendingLabel: tf("sending"),
        }) +
        `</form>`;

  const update = () => {
    root = morph(root, render());
    if (state === "done") (root as HTMLElement).focus();
  };

  const container = root.parentElement ?? document.body;
  container.addEventListener("submit", async (e) => {
    if (!(e.target instanceof Node) || !root.contains(e.target)) return;
    e.preventDefault();
    state = "pending";
    update();
    const res = await fetch("/api/newsletter/unsubscribe", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ token: props.token }),
    }).catch(() => null);
    state = res?.ok ? "done" : "error";
    update();
  });
  update();
}
