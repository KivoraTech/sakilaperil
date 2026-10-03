/**
 * Shared runtime for the public engagement islands (forms, newsletter, donate widget).
 *
 * Each island renders its whole markup from a template that mirrors the reference React component
 * (components/engagement/*.tsx) element by element, and applies it with idiomorph — so the DOM
 * after every state change is exactly what React would have produced, while focus and typed values
 * survive. The Blade partials (resources/views/engagement/*) server-render the same initial state.
 */
import { cn } from "cn";
import { Idiomorph } from "idiomorph";
import iconMarkup from "./icons.json";

export { cn };

// ── markup helpers ──────────────────────────────────────────────────────────────────────

const ESC: Record<string, string> = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#x27;" };

/** Escape text / attribute values like React does. */
export function esc(value: unknown): string {
  return String(value ?? "").replace(/[&<>"']/g, (c) => ESC[c]!);
}

type AttrValue = string | number | boolean | null | undefined;

/** Attributes the way React serializes them: false/null/undefined omitted, true → name="". */
export function attrs(map: Record<string, AttrValue>): string {
  let out = "";
  for (const [name, value] of Object.entries(map)) {
    if (value === false || value === null || value === undefined) continue;
    out += value === true ? ` ${name}=""` : ` ${name}="${esc(value)}"`;
  }
  return out;
}

/** Lucide icon markup identical to lucide-react's (see generate-icons.tsx). */
export function icon(name: keyof typeof iconMarkup, className: string): string {
  return iconMarkup[name].replace("__CLASS__", esc(className));
}

// ── messages ────────────────────────────────────────────────────────────────────────────

type Dict = Record<string, unknown>;
let dictionary: Dict | null = null;

function loadDictionary(): Dict {
  if (dictionary) return dictionary;
  const el = document.getElementById("engagement-messages");
  try {
    dictionary = el?.textContent ? (JSON.parse(el.textContent) as Dict) : {};
  } catch {
    dictionary = {};
  }
  return dictionary;
}

function lookup(path: string): unknown {
  let node: unknown = loadDictionary();
  for (const part of path.split(".")) {
    if (!node || typeof node !== "object") return undefined;
    node = (node as Dict)[part];
  }
  return node;
}

export type Translator = ((key: string, params?: Record<string, string | number>) => string) & {
  has: (key: string) => boolean;
};

/** next-intl style `useTranslations(namespace)` over the messages the page embedded. */
export function translations(namespace: string): Translator {
  const t = ((key: string, params?: Record<string, string | number>) => {
    const raw = lookup(`${namespace}.${key}`);
    if (typeof raw !== "string") return `${namespace}.${key}`;
    return raw.replace(/\{(\w+)\}/g, (m, name: string) =>
      params && name in params ? String(params[name]) : m,
    );
  }) as Translator;
  t.has = (key: string) => typeof lookup(`${namespace}.${key}`) === "string";
  return t;
}

// ── DOM morphing ────────────────────────────────────────────────────────────────────────

/**
 * Replace `root` with `html` (one element). Uncontrolled islands keep the user's input values
 * (React Hook Form `register()` semantics); Turnstile's iframe is never touched. Returns the
 * (possibly new) root element.
 */
export function morph(root: Element, html: string, opts: { uncontrolled?: boolean } = {}): Element {
  const result = Idiomorph.morph(root, html, {
    morphStyle: "outerHTML",
    callbacks: {
      beforeNodeMorphed: (oldNode: Node) =>
        !(oldNode instanceof HTMLElement && oldNode.dataset.turnstileSlot !== undefined),
      beforeAttributeUpdated: (name: string, node: Element) => {
        if (!opts.uncontrolled) return true;
        const control =
          node instanceof HTMLInputElement ||
          node instanceof HTMLTextAreaElement ||
          node instanceof HTMLSelectElement ||
          node instanceof HTMLOptionElement;
        return !(control && (name === "value" || name === "checked" || name === "selected"));
      },
    },
  });
  // When the root element type changes (form → success <p>), idiomorph inserts a new node.
  if (root.isConnected) return root;
  const created = (Array.isArray(result) ? result : []).find((n): n is Element => n instanceof Element);
  return created ?? root;
}

// ── submission plumbing (port of use-public-submit.ts) ─────────────────────────────────

export type SubmitError =
  { kind: "fields"; fieldErrors: Record<string, string[]> } | { kind: "message"; code: string };
export type SubmitResult<T> = { ok: true; data: T } | { ok: false; error: SubmitError };

/** Anti-spam state of one form: honeypot value, form-open time, Turnstile token. */
export class PublicSubmit {
  startedAt = 0;
  honeypot = "";
  turnstileToken: string | undefined;
  pending = false;

  /** Record when the visitor first interacts / the form mounts (time trap). */
  markStart = () => {
    if (!this.startedAt) this.startedAt = Date.now();
  };

  async submit<T = Record<string, unknown>>(
    url: string,
    payload: Record<string, unknown>,
    onPending: (pending: boolean) => void,
  ): Promise<SubmitResult<T>> {
    this.pending = true;
    onPending(true);
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({
          ...payload,
          hp_extra: this.honeypot,
          startedAt: this.startedAt || Date.now(),
          turnstileToken: this.turnstileToken,
        }),
      });
      const json = (await res.json().catch(() => ({}))) as {
        ok?: boolean;
        code?: string;
        fieldErrors?: Record<string, string[]>;
      } & T;
      if (res.ok && json.ok) return { ok: true, data: json };
      if (json.code === "VALIDATION" && json.fieldErrors && Object.keys(json.fieldErrors).length) {
        return { ok: false, error: { kind: "fields", fieldErrors: json.fieldErrors } };
      }
      return { ok: false, error: { kind: "message", code: json.code ?? "ERROR" } };
    } catch {
      return { ok: false, error: { kind: "message", code: "ERROR" } };
    } finally {
      this.pending = false;
      onPending(false);
    }
  }
}

/** Map an API error code to a `forms.*` dictionary key. */
export function errorMessageKey(code: string): string {
  switch (code) {
    case "RATE_LIMITED":
      return "rateLimited";
    case "SPAM":
      return "spam";
    case "CAPTCHA":
      return "captcha";
    case "NOT_CONFIGURED":
      return "notConfigured";
    default:
      return "genericError";
  }
}

/** Loads Cloudflare Turnstile into a `[data-turnstile-slot]` element (only when a site key is set). */
export function mountTurnstile(root: Element, siteKey: string | undefined, onToken: (token: string) => void) {
  if (!siteKey) return;
  const w = window as unknown as { turnstile?: { render: (el: HTMLElement, o: object) => void } };
  const render = () => {
    const slot = root.querySelector<HTMLElement>("[data-turnstile-slot]");
    if (slot && w.turnstile && !slot.hasChildNodes()) {
      w.turnstile.render(slot, { sitekey: siteKey, callback: onToken, "refresh-expired": "auto" });
      slot.dataset.ready = "true";
    }
  };
  if (w.turnstile) return render();
  const existing = document.querySelector<HTMLScriptElement>("script[data-turnstile]");
  const script = existing ?? document.createElement("script");
  if (!existing) {
    script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
    script.async = true;
    script.defer = true;
    script.dataset.turnstile = "1";
    document.head.appendChild(script);
  }
  script.addEventListener("load", render);
}

/** Props JSON of an island: the `<script type="application/json" data-engagement-props>` right before it. */
export function readProps<T>(root: Element): T | null {
  const prev = root.previousElementSibling;
  if (!(prev instanceof HTMLScriptElement) || prev.dataset.engagementProps === undefined) return null;
  try {
    return JSON.parse(prev.textContent ?? "") as T;
  } catch {
    return null;
  }
}

/** Money like lib/payments/schemas.ts formatMoney (Intl, so identical to the reference). */
export function formatMoney(minor: number, currency: string, locale: string): string {
  const major = minor / 100;
  return new Intl.NumberFormat(locale === "en" ? "en-GB" : "it-IT", {
    style: "currency",
    currency,
    minimumFractionDigits: major % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(major);
}
