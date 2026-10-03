/**
 * Port of components/engagement/SubmissionForm.tsx: contact / volunteer / membership / partner.
 * Same markup, same validation (the shared Zod schemas), same React Hook Form behaviour
 * (mode "onTouched": validate a field on first blur, then on every change; error summary focused
 * after a failed submit, never RHF's own focus).
 */
import type { z } from "zod";
import type { FormType } from "@/lib/forms/schemas";
import { PublicSubmit, attrs, cn, errorMessageKey, esc, morph, mountTurnstile, translations } from "./core";
import {
  checkbox,
  errorSummary,
  field,
  honeypot,
  inputClass,
  privacyConsentLabel,
  submitButton,
  successPanel,
  turnstileSlot,
} from "./ui";

// Option lists of resources/js/lib/forms/schemas.ts (kept here so Zod loads only on first interaction).
const contactSubjects = ["info", "donations", "volunteering", "partnership", "press", "other"] as const;
const membershipTypes = ["ordinary", "supporting"] as const;
const partnershipTypes = ["restaurant", "company", "school", "association", "other"] as const;

/** The shared Zod schemas, loaded lazily (≈25 KB gzip) the first time a form is used. */
const loadSchemas = () => import("@/lib/forms/schemas");

type FieldDef = {
  name: string;
  label: string; // forms.* key
  kind: "text" | "email" | "tel" | "url" | "textarea" | "select";
  optional?: boolean;
  autoComplete?: string;
  options?: readonly string[];
  optionsNs?: string; // forms.<ns>.<option>
  hint?: string;
  wide?: boolean;
  rows?: number;
};

export const FIELDS: Record<FormType, FieldDef[]> = {
  contact: [
    { name: "name", label: "name", kind: "text", autoComplete: "name" },
    { name: "email", label: "email", kind: "email", autoComplete: "email" },
    { name: "phone", label: "phone", kind: "tel", optional: true, autoComplete: "tel" },
    {
      name: "subject",
      label: "subject",
      kind: "select",
      optional: true,
      options: contactSubjects,
      optionsNs: "subjects",
    },
    { name: "message", label: "message", kind: "textarea", wide: true, rows: 6 },
  ],
  volunteer: [
    { name: "firstName", label: "firstName", kind: "text", autoComplete: "given-name" },
    { name: "lastName", label: "lastName", kind: "text", autoComplete: "family-name" },
    { name: "email", label: "email", kind: "email", autoComplete: "email" },
    { name: "phone", label: "phone", kind: "tel", optional: true, autoComplete: "tel" },
    { name: "city", label: "city", kind: "text", optional: true, autoComplete: "address-level2" },
    { name: "country", label: "country", kind: "text", optional: true, autoComplete: "country-name" },
    { name: "skills", label: "skills", kind: "textarea", optional: true, wide: true, rows: 3 },
    {
      name: "availability",
      label: "availability",
      kind: "text",
      optional: true,
      wide: true,
      hint: "availabilityHint",
    },
    { name: "message", label: "message", kind: "textarea", optional: true, wide: true, rows: 4 },
  ],
  membership: [
    { name: "firstName", label: "firstName", kind: "text", autoComplete: "given-name" },
    { name: "lastName", label: "lastName", kind: "text", autoComplete: "family-name" },
    { name: "email", label: "email", kind: "email", autoComplete: "email" },
    { name: "phone", label: "phone", kind: "tel", optional: true, autoComplete: "tel" },
    {
      name: "membershipType",
      label: "membershipType",
      kind: "select",
      options: membershipTypes,
      optionsNs: "membershipTypes",
      wide: true,
    },
    {
      name: "address",
      label: "address",
      kind: "text",
      optional: true,
      autoComplete: "street-address",
      wide: true,
    },
    { name: "city", label: "city", kind: "text", optional: true, autoComplete: "address-level2" },
    { name: "country", label: "country", kind: "text", optional: true, autoComplete: "country-name" },
    { name: "message", label: "message", kind: "textarea", optional: true, wide: true, rows: 3 },
  ],
  partner: [
    { name: "organization", label: "organization", kind: "text", autoComplete: "organization" },
    { name: "contactName", label: "contactName", kind: "text", autoComplete: "name" },
    { name: "email", label: "email", kind: "email", autoComplete: "email" },
    { name: "phone", label: "phone", kind: "tel", optional: true, autoComplete: "tel" },
    { name: "website", label: "website", kind: "url", optional: true, autoComplete: "url" },
    {
      name: "partnershipType",
      label: "partnershipType",
      kind: "select",
      optional: true,
      options: partnershipTypes,
      optionsNs: "partnershipTypes",
    },
    { name: "message", label: "message", kind: "textarea", wide: true, rows: 5 },
  ],
};

export type SubmissionFormProps = {
  type: FormType;
  locale: string;
  source?: string;
  privacyHref: string;
  successMessage?: string;
  intro?: string;
  showTitle?: boolean;
  turnstileSiteKey?: string;
  className?: string;
};

export function mountSubmissionForm(initialRoot: Element, props: SubmissionFormProps) {
  const t = translations("forms");
  const { type } = props;
  const showTitle = props.showTitle ?? true;
  const fields = FIELDS[type];
  let schema: z.ZodType | null = null;
  const ensureSchema = async () => {
    schema ??= (await loadSchemas()).formSchemas[type] as z.ZodType;
    return schema;
  };
  const idPrefix = `${type}-form`;
  const spam = new PublicSubmit();

  let root = initialRoot;
  let errors: Record<string, string> = {};
  const touched = new Set<string>();
  let submitCount = 0;
  let formError: string | undefined;
  let pending = false;
  let done: { name?: string } | null = null;
  let focusAfterRender: "summary" | "success" | null = null;

  const values = (): Record<string, unknown> => {
    const form = root.querySelector("form");
    const out: Record<string, unknown> = {};
    for (const f of fields) {
      const el = form?.elements.namedItem(f.name) as HTMLInputElement | null;
      out[f.name] = el?.value ?? "";
    }
    const consent = form?.elements.namedItem("consent") as HTMLInputElement | null;
    out.consent = !!consent?.checked;
    return out;
  };

  /** Zod over all values; errors for `only` (or every field) like RHF's resolver + trigger(). */
  const validate = (only?: string) => {
    if (!schema) return true; // not loaded yet: validated again (awaited) on submit
    const r = schema.safeParse(values());
    const next: Record<string, string> = {};
    if (!r.success) {
      for (const issue of r.error.issues) {
        const k = String(issue.path[0] ?? "");
        if (k && !next[k]) next[k] = issue.message;
      }
    }
    if (only) {
      if (next[only]) errors = { ...errors, [only]: next[only]! };
      else {
        const { [only]: _removed, ...rest } = errors;
        errors = rest;
      }
    } else errors = next;
    return r.success;
  };

  const errText = (name: string): string | undefined => {
    const key = errors[name];
    if (!key) return undefined;
    return t.has(`errors.${key}`) ? t(`errors.${key}`) : t("errors.invalid");
  };
  const fieldLabel = (name: string) => {
    if (name === "consent") return t("privacyLink");
    const def = fields.find((f) => f.name === name);
    return def ? t(def.label) : name;
  };

  const render = (): string => {
    if (done) {
      const name = done.name;
      return (
        `<div${attrs({ class: props.className, "data-engagement": `${type}-form` })}>` +
        successPanel(
          name ? t("successTitle", { name }) : t("successTitleAnon"),
          props.successMessage || t(`${type}.success`),
          `<button type="button" data-action="send-another" class="text-[15px] font-semibold text-himalaya underline underline-offset-4 hover:text-sindoor focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">${esc(t("sendAnother"))}</button>`,
        ) +
        `</div>`
      );
    }
    const errorItems = [...fields.map((f) => f.name), "consent"]
      .map((name) => ({ name, message: errText(name) }))
      .filter((e): e is { name: string; message: string } => !!e.message)
      .map((e) => ({ fieldId: `${idPrefix}-${e.name}`, message: `${fieldLabel(e.name)}: ${e.message}` }));
    const titleId = `${idPrefix}-title`;
    // The intro belongs to the form's own title. When a block supplies the heading (showTitle=false)
    // it also shows the introduction itself, so repeating it here would print it twice.
    const intro = showTitle ? props.intro || (t.has(`${type}.intro`) ? t(`${type}.intro`) : "") : "";

    return (
      `<div${attrs({ class: cn("relative", props.className), "data-engagement": `${type}-form` })}>` +
      (showTitle
        ? `<h3${attrs({ id: titleId, class: "font-serif text-2xl font-semibold text-heading" })}>${esc(t(`${type}.title`))}</h3>`
        : "") +
      (intro ? `<p class="mt-2 max-w-prose text-base leading-relaxed text-fg-muted">${esc(intro)}</p>` : "") +
      `<form${attrs({
        novalidate: true,
        "aria-labelledby": showTitle ? titleId : undefined,
        "aria-label": showTitle ? undefined : t(`${type}.title`),
        class: "mt-6 flex flex-col gap-5",
      })}>` +
      (formError || (submitCount > 0 && errorItems.length > 0)
        ? errorSummary({
            id: `${idPrefix}-errors`,
            title: t("errorSummary"),
            items: formError ? [] : errorItems,
            message: formError,
          })
        : "") +
      `<div class="grid gap-5 sm:grid-cols-2">` +
      fields
        .map((f) =>
          field({
            id: `${idPrefix}-${f.name}`,
            label: t(f.label),
            optionalLabel: f.optional ? t("optional") : undefined,
            error: errText(f.name),
            hint: f.hint ? t(f.hint) : undefined,
            className: f.wide || f.kind === "textarea" ? "sm:col-span-2" : undefined,
            control: ({ describedBy, invalid }) => {
              const common = {
                id: `${idPrefix}-${f.name}`,
                "aria-describedby": describedBy,
                "aria-invalid": invalid ? "true" : undefined,
                "aria-required": f.optional ? undefined : "true",
              };
              if (f.kind === "textarea")
                return `<textarea${attrs({ rows: f.rows ?? 4, ...common, class: cn(inputClass, "resize-y"), name: f.name })}></textarea>`;
              if (f.kind === "select")
                return (
                  `<select${attrs({ ...common, class: cn(inputClass, "appearance-auto"), name: f.name })}><option value="">—</option>` +
                  f
                    .options!.map(
                      (o) => `<option value="${esc(o)}">${esc(t(`${f.optionsNs}.${o}`))}</option>`,
                    )
                    .join("") +
                  `</select>`
                );
              return `<input${attrs({
                type: f.kind,
                autocomplete: f.autoComplete,
                inputmode: f.kind === "tel" ? "tel" : f.kind === "email" ? "email" : undefined,
                ...common,
                class: inputClass,
                name: f.name,
              })}/>`;
            },
          }),
        )
        .join("") +
      `</div>` +
      checkbox({
        id: `${idPrefix}-consent`,
        ariaRequired: true,
        error: errText("consent"),
        name: "consent",
        labelHtml: privacyConsentLabel({
          before: t("privacyConsentBefore"),
          link: t("privacyLink"),
          after: t("privacyConsentAfter"),
          href: props.privacyHref,
        }),
      }) +
      honeypot(t("honeypotLabel"), spam.honeypot) +
      turnstileSlot(props.turnstileSiteKey) +
      `<div>${submitButton({ pending, label: t(`${type}.submit`), pendingLabel: t("sending") })}</div>` +
      `</form></div>`
    );
  };

  const update = () => {
    root = morph(root, render(), { uncontrolled: true });
    mountTurnstile(root, props.turnstileSiteKey, (token) => (spam.turnstileToken = token));
    const target = focusAfterRender;
    focusAfterRender = null;
    if (target === "summary") {
      // Focus by id after paint, like the reference (requestAnimationFrame).
      requestAnimationFrame(() => document.getElementById(`${idPrefix}-errors`)?.focus());
    } else if (target === "success") {
      root.querySelector<HTMLElement>('[data-testid="form-success"]')?.focus();
    }
  };

  // Set synchronously, before the first await: three submit events in one tick must send one request.
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
    submitCount += 1;
    await ensureSchema();
    if (!validate()) {
      focusAfterRender = "summary";
      update();
      return;
    }
    formError = undefined;
    const vals = values();
    const payload: Record<string, unknown> = { ...vals, locale: props.locale, source: props.source };
    for (const f of fields) if (payload[f.name] === "") delete payload[f.name];
    const res = await spam.submit(`/api/forms/${type}`, payload, (p) => {
      pending = p;
      update();
    });
    if (res.ok) {
      const name = String(vals.firstName ?? vals.contactName ?? vals.name ?? "");
      done = { name: name.split(/\s+/)[0] || undefined };
      focusAfterRender = "success";
      update();
      return;
    }
    if (res.error.kind === "fields") {
      for (const [name, msgs] of Object.entries(res.error.fieldErrors)) {
        if (msgs?.[0]) errors = { ...errors, [name]: msgs[0] };
      }
    } else {
      formError = t(errorMessageKey(res.error.code));
    }
    focusAfterRender = "summary";
    update();
  };

  // Event delegation on the island's container (survives re-renders).
  const container = root.parentElement ?? document.body;
  const inside = (e: Event) => e.target instanceof Node && root.contains(e.target);
  container.addEventListener("submit", (e) => inside(e) && void onSubmit(e));
  container.addEventListener("focusin", (e) => {
    if (!inside(e)) return;
    spam.markStart();
    void ensureSchema();
  });
  // A field usually loses focus because the visitor pressed on something else. Showing its error
  // right then shifts the layout between "press" and "release", so that click (e.g. on the consent
  // checkbox) would land on nothing. While a pointer is down the re-render waits for its release.
  let pointerDown = false;
  let renderAfterPointer = false;
  container.addEventListener("pointerdown", (e) => inside(e) && (pointerDown = true), true);
  const onPointerEnd = () => {
    pointerDown = false;
    if (!renderAfterPointer) return;
    renderAfterPointer = false;
    setTimeout(update, 0); // after the click event that follows pointerup
  };
  window.addEventListener("pointerup", onPointerEnd, true);
  window.addEventListener("pointercancel", onPointerEnd, true);
  container.addEventListener("focusout", (e) => {
    if (!inside(e)) return;
    const name = (e.target as HTMLInputElement).name;
    if (!name || name === "hp_extra" || done) return;
    if (!touched.has(name)) {
      touched.add(name);
      validate(name);
      if (pointerDown) renderAfterPointer = true;
      else update();
    }
  });
  const onEdit = (e: Event) => {
    if (!inside(e) || done) return;
    const target = e.target as HTMLInputElement;
    if (target.name === "hp_extra") {
      spam.honeypot = target.value;
      return;
    }
    if (target.name && (touched.has(target.name) || submitCount > 0)) {
      validate(target.name);
      update();
    }
  };
  container.addEventListener("input", onEdit);
  container.addEventListener("change", onEdit);
  container.addEventListener("click", (e) => {
    if (!inside(e) || !(e.target instanceof Element) || !e.target.closest('[data-action="send-another"]'))
      return;
    done = null;
    errors = {};
    touched.clear();
    submitCount = 0;
    formError = undefined;
    update();
  });

  spam.markStart();
  update();
}
