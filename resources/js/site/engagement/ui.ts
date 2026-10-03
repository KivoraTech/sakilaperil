/**
 * Accessible form primitives (port of components/engagement/ui.tsx + styles.ts) as HTML templates.
 * Labels above fields, 48px inputs, 1.5px line-strong border, visible two-tone focus, errors linked
 * with aria-describedby + aria-invalid, optional fields marked "(optional)".
 */
import { attrs, cn, esc, icon } from "./core";

/**
 * Re-maps the semantic colour tokens to their light-surface values for components that paint
 * their own light background (donate widget, bank panel, success/error panels).
 */
export const lightScope =
  "[--color-fg:var(--color-ink)] [--color-fg-muted:var(--color-ink-soft)] [--color-heading:var(--color-himalaya)] " +
  "[--color-focus:var(--color-himalaya)] [--color-border-input:var(--color-line-strong)]";

export const inputClass =
  "block min-h-12 w-full rounded-[10px] border-[1.5px] border-border-input bg-white px-3.5 py-2.5 text-base text-ink " +
  "placeholder:text-ink-soft/70 transition-colors " +
  "focus-visible:border-himalaya focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus " +
  "aria-invalid:border-error disabled:cursor-not-allowed disabled:opacity-60";

export const HONEYPOT_FIELD = "hp_extra";

export type FieldIds = { id: string; describedBy: string | undefined; invalid: boolean };

/** <Field>: label (+ optional marker), control, hint, error. */
export function field(o: {
  id: string;
  label: string;
  error?: string;
  hint?: string;
  optionalLabel?: string;
  className?: string;
  control: (ids: FieldIds) => string;
}): string {
  const hintId = o.hint ? `${o.id}-hint` : undefined;
  const errorId = o.error ? `${o.id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;
  return (
    `<div class="${esc(cn("flex flex-col gap-1.5", o.className))}">` +
    `<label${attrs({ for: o.id, class: "text-[15px] font-medium text-fg" })}>${esc(o.label)}` +
    (o.optionalLabel ? `<span class="ml-1 font-normal text-fg-muted">${esc(o.optionalLabel)}</span>` : "") +
    `</label>` +
    o.control({ id: o.id, describedBy, invalid: !!o.error }) +
    (o.hint ? `<p${attrs({ id: hintId, class: "text-sm text-fg-muted" })}>${esc(o.hint)}</p>` : "") +
    (o.error
      ? `<p${attrs({ id: errorId, class: "flex items-start gap-1.5 text-sm font-medium text-error" })}>` +
        icon("AlertCircle", "mt-0.5 size-4 shrink-0") +
        `${esc(o.error)}</p>`
      : "") +
    `</div>`
  );
}

/** <Checkbox>: input + label (HTML), optional error. */
export function checkbox(o: {
  id: string;
  labelHtml: string;
  error?: string;
  name?: string;
  checked?: boolean;
  ariaRequired?: boolean;
}): string {
  const errorId = o.error ? `${o.id}-error` : undefined;
  return (
    `<div class="flex flex-col gap-1"><div class="flex items-start gap-3">` +
    `<input${attrs({
      id: o.id,
      type: "checkbox",
      "aria-invalid": o.error ? "true" : undefined,
      "aria-describedby": errorId,
      class:
        "mt-0.5 size-5 shrink-0 cursor-pointer rounded border-[1.5px] border-border-input accent-sindoor focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus",
      "aria-required": o.ariaRequired ? "true" : undefined,
      checked: o.checked,
      name: o.name,
    })}/>` +
    `<label${attrs({ for: o.id, class: "cursor-pointer text-[15px] leading-snug text-fg" })}>${o.labelHtml}</label>` +
    `</div>` +
    (o.error
      ? `<p${attrs({ id: errorId, class: "ml-8 flex items-start gap-1.5 text-sm font-medium text-error" })}>` +
        icon("AlertCircle", "mt-0.5 size-4 shrink-0") +
        `${esc(o.error)}</p>`
      : "") +
    `</div>`
  );
}

/** Visually hidden honeypot. Humans never see or tab into it; naive bots fill it. */
export function honeypot(label: string, value: string): string {
  return (
    `<div aria-hidden="true" class="absolute -left-[10000px] h-px w-px overflow-hidden"><label>${esc(label)}` +
    `<input${attrs({ type: "text", tabindex: "-1", autocomplete: "off", name: HONEYPOT_FIELD, value })}/>` +
    `</label></div>`
  );
}

/** Error summary that receives focus after a failed submit (WCAG 3.3.1). */
export function errorSummary(o: {
  id?: string;
  title: string;
  items: { fieldId?: string; message: string }[];
  message?: string;
}): string {
  if (!o.items.length && !o.message) return "";
  return (
    `<div${attrs({
      id: o.id,
      tabindex: "-1",
      role: "alert",
      class: cn(
        lightScope,
        "rounded-[14px] border-[1.5px] border-error bg-error-bg p-4 text-[15px] text-error focus:outline-2 focus:outline-offset-2 focus:outline-focus",
      ),
    })}>` +
    `<p class="flex items-center gap-2 font-semibold">${icon("AlertCircle", "size-5 shrink-0")}${esc(o.message ?? o.title)}</p>` +
    (o.items.length
      ? `<ul class="mt-2 list-disc space-y-1 pl-10">` +
        o.items
          .map(
            (i) =>
              `<li>${
                i.fieldId
                  ? `<a class="underline underline-offset-2" href="#${esc(i.fieldId)}">${esc(i.message)}</a>`
                  : esc(i.message)
              }</li>`,
          )
          .join("") +
        `</ul>`
      : "") +
    `</div>`
  );
}

/** Success panel that replaces the form and receives focus. */
export function successPanel(title: string, body: string, actionHtml?: string): string {
  return (
    `<div${attrs({
      tabindex: "-1",
      role: "status",
      "data-testid": "form-success",
      class: cn(
        lightScope,
        "rounded-[20px] border border-success/30 bg-success-bg p-6 text-success focus:outline-2 focus:outline-offset-2 focus:outline-focus sm:p-8",
      ),
    })}>` +
    icon("CheckCircle2", "size-9 text-success") +
    `<p class="mt-3 font-serif text-2xl font-semibold text-heading">${esc(title)}</p>` +
    `<p class="mt-2 text-base leading-relaxed">${esc(body)}</p>` +
    (actionHtml ? `<div class="mt-4">${actionHtml}</div>` : "") +
    `</div>`
  );
}

export function submitButton(o: { pending: boolean; label: string; pendingLabel: string }): string {
  return (
    `<button${attrs({
      type: "submit",
      disabled: o.pending,
      "aria-disabled": o.pending ? "true" : "false",
      class:
        "inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-sindoor px-7 py-3 text-base font-semibold text-white shadow-[0_8px_20px_-8px_rgba(181,69,47,0.6)] transition-colors hover:bg-sindoor-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-wait disabled:opacity-70",
    })}>` +
    (o.pending
      ? `<span aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white motion-reduce:animate-none"></span>${esc(o.pendingLabel)}`
      : esc(o.label)) +
    `</button>`
  );
}

/** Turnstile container (only when a site key is configured); the widget renders into it. */
export function turnstileSlot(siteKey: string | undefined): string {
  return siteKey ? `<div data-turnstile-slot="" class="min-h-[65px]"></div>` : "";
}

/** Privacy consent label: "Ho letto l'<a>informativa sulla privacy</a> e acconsento …". */
export function privacyConsentLabel(o: {
  before: string;
  link: string;
  after: string;
  href: string;
}): string {
  return (
    esc(o.before) +
    `<a${attrs({
      href: o.href,
      target: "_blank",
      rel: "noopener",
      class: "font-medium text-himalaya underline underline-offset-2",
    })}>${esc(o.link)}</a>` +
    esc(o.after)
  );
}
