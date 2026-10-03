import type { z } from "zod";

/**
 * Turn a block's Zod schema (lib/blocks/schemas.ts) into a field tree the admin form renders.
 * Pure and unit-tested. Forms are GENERATED from the same schemas the server validates with,
 * so a new optional field in a block schema appears in the editor automatically.
 *
 * Special shapes are recognised structurally:
 * - `{ assetId, alt? }`         → image picker (MediaAsset reference)
 * - `{ label, href }`           → link (button) editor
 * - `{ type: "doc", content }`  → rich-text (Tiptap) editor
 * - keys listed in REFERENCE_KEYS → picker of projects / albums / news categories
 */

export type ReferenceKind = "project" | "album" | "newsCategory";

export type FieldSpec = {
  optional: boolean;
  defaultValue?: unknown;
} & (
  | { kind: "text"; min?: number; max?: number; multiline: boolean; pattern?: string }
  | { kind: "number"; min?: number; max?: number; int: boolean; step?: number }
  | { kind: "boolean" }
  | { kind: "enum"; options: string[] }
  | { kind: "enumArray"; options: string[]; min?: number; max?: number }
  | { kind: "image" }
  | { kind: "link" }
  | { kind: "richText" }
  | { kind: "reference"; ref: ReferenceKind; multiple: boolean; max?: number }
  | { kind: "object"; fields: Record<string, FieldSpec> }
  | { kind: "array"; item: FieldSpec; min?: number; max?: number }
  | { kind: "unknown" }
);

export const REFERENCE_KEYS: Record<string, { ref: ReferenceKind; multiple: boolean }> = {
  highlightProjectId: { ref: "project", multiple: false },
  projectId: { ref: "project", multiple: false },
  projectIds: { ref: "project", multiple: true },
  albumId: { ref: "album", multiple: false },
  categoryId: { ref: "newsCategory", multiple: false },
};

/** Texts longer than this are edited in a textarea. */
const MULTILINE_OVER = 300;

type AnyDef = { type: string; [k: string]: unknown };
const defOf = (s: unknown): AnyDef => (s as { _zod: { def: AnyDef } })._zod.def;

type Check = { _zod: { def: { check: string; minimum?: number; maximum?: number; value?: number } } };
function lengthBounds(def: AnyDef) {
  let min: number | undefined;
  let max: number | undefined;
  for (const c of (def.checks as Check[] | undefined) ?? []) {
    const d = c._zod.def;
    if (d.check === "min_length") min = d.minimum;
    if (d.check === "max_length") max = d.maximum;
  }
  return { min, max };
}

function isImageShape(shape: Record<string, unknown>) {
  const keys = Object.keys(shape);
  return (
    keys.includes("assetId") &&
    keys.every((k) => ["assetId", "alt", "caption"].includes(k)) &&
    keys.length <= 2
  );
}
function isLinkShape(shape: Record<string, unknown>) {
  const keys = Object.keys(shape).sort();
  return keys.length === 2 && keys[0] === "href" && keys[1] === "label";
}
function isRichTextShape(shape: Record<string, unknown>) {
  const t = shape.type ? defOf(shape.type) : null;
  return !!t && t.type === "literal" && (t.values as unknown[])?.includes("doc") && "content" in shape;
}

export function describeSchema(schema: unknown, key?: string): FieldSpec {
  let current: unknown = schema;
  let optional = false;
  let defaultValue: unknown;

  // Unwrap optional / default / nullable wrappers (outermost first).
  for (let i = 0; i < 6; i++) {
    const def = defOf(current);
    if (def.type === "optional" || def.type === "nullable") {
      optional = true;
      current = def.innerType;
    } else if (def.type === "default" || def.type === "prefault") {
      const dv = def.defaultValue;
      defaultValue = typeof dv === "function" ? (dv as () => unknown)() : dv;
      current = def.innerType;
    } else if (def.type === "pipe") {
      current = def.in;
    } else break;
  }

  const base = { optional, defaultValue };
  const def = defOf(current);
  const ref = key ? REFERENCE_KEYS[key] : undefined;

  switch (def.type) {
    case "string": {
      if (ref && !ref.multiple) return { ...base, kind: "reference", ref: ref.ref, multiple: false };
      const s = current as { minLength?: number | null; maxLength?: number | null };
      const max = s.maxLength ?? undefined;
      return {
        ...base,
        kind: "text",
        min: s.minLength ?? undefined,
        max,
        multiline: (max ?? 0) > MULTILINE_OVER,
      };
    }
    case "number": {
      const n = current as { minValue?: number | null; maxValue?: number | null; isInt?: boolean };
      const min = Number.isFinite(n.minValue) ? (n.minValue as number) : undefined;
      const max = Number.isFinite(n.maxValue) ? (n.maxValue as number) : undefined;
      const int = !!n.isInt;
      return {
        ...base,
        kind: "number",
        min,
        max,
        int,
        step: int ? 1 : max !== undefined && max <= 1 ? 0.01 : undefined,
      };
    }
    case "boolean":
      return { ...base, kind: "boolean" };
    case "enum":
      return { ...base, kind: "enum", options: Object.values(def.entries as Record<string, string>) };
    case "literal":
      return { ...base, kind: "unknown" };
    case "object": {
      const shape = def.shape as Record<string, unknown>;
      if (isRichTextShape(shape)) return { ...base, kind: "richText" };
      if (isImageShape(shape)) return { ...base, kind: "image" };
      if (isLinkShape(shape)) return { ...base, kind: "link" };
      const fields: Record<string, FieldSpec> = {};
      for (const [k, v] of Object.entries(shape)) fields[k] = describeSchema(v, k);
      return { ...base, kind: "object", fields };
    }
    case "array": {
      const { min, max } = lengthBounds(def);
      const element = def.element;
      const edef = defOf(element);
      if (ref?.multiple) return { ...base, kind: "reference", ref: ref.ref, multiple: true, max };
      if (edef.type === "enum")
        return {
          ...base,
          kind: "enumArray",
          options: Object.values(edef.entries as Record<string, string>),
          min,
          max,
        };
      return { ...base, kind: "array", item: describeSchema(element), min, max };
    }
    default:
      return { ...base, kind: "unknown" };
  }
}

/** Describe a block's `{ content, settings }` pair. */
export function describeBlock(def: { content: z.ZodType; settings: z.ZodType }) {
  const content = describeSchema(def.content);
  const settings = describeSchema(def.settings);
  return {
    content: content.kind === "object" ? content.fields : {},
    settings: settings.kind === "object" ? settings.fields : {},
  };
}

/** A sensible empty value for a new array item / newly enabled optional object. */
export function emptyValue(spec: FieldSpec): unknown {
  if (spec.defaultValue !== undefined) return structuredClone(spec.defaultValue);
  switch (spec.kind) {
    case "text":
      return "";
    case "number":
      return spec.min ?? 0;
    case "boolean":
      return false;
    case "enum":
      return spec.options[0];
    case "enumArray":
      return [];
    case "image":
      return { assetId: "" };
    case "link":
      return { label: "", href: "" };
    case "richText":
      return { type: "doc", content: [] };
    case "reference":
      return spec.multiple ? [] : "";
    case "array":
      return [];
    case "object": {
      const out: Record<string, unknown> = {};
      for (const [k, f] of Object.entries(spec.fields)) if (!f.optional) out[k] = emptyValue(f);
      return out;
    }
    default:
      return undefined;
  }
}

/**
 * Remove empty optional values before validating/saving: "" strings, empty optional objects
 * (e.g. a link with neither label nor href) and NaN numbers. Required fields are kept so the
 * validator can flag them.
 */
export function cleanValue(value: unknown, spec?: FieldSpec): unknown {
  if (Array.isArray(value)) {
    const item = spec?.kind === "array" ? spec.item : undefined;
    return value.map((v) => cleanValue(v, item)).filter((v) => v !== undefined);
  }
  if (typeof value === "number" && Number.isNaN(value)) return undefined;
  if (typeof value === "string") {
    const trimmed = value;
    if (trimmed.trim() === "" && spec?.optional) return undefined;
    return trimmed;
  }
  if (value && typeof value === "object") {
    if (spec?.kind === "richText") return value;
    const fields = spec?.kind === "object" ? spec.fields : undefined;
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(value as Record<string, unknown>)) {
      const childSpec: FieldSpec | undefined =
        fields?.[k] ??
        (spec?.kind === "image" || spec?.kind === "link"
          ? ({
              kind: "text",
              optional: spec.kind === "image" && k !== "assetId",
              multiline: false,
            } as FieldSpec)
          : undefined);
      const cleaned = cleanValue(v, childSpec ?? ({ kind: "unknown", optional: true } as FieldSpec));
      if (cleaned !== undefined) out[k] = cleaned;
    }
    if (spec?.optional && (spec.kind === "link" || spec.kind === "image" || spec.kind === "object")) {
      const meaningful = Object.values(out).some((v) => v !== undefined && v !== "" && v !== false);
      if (!meaningful) return undefined;
    }
    return out;
  }
  return value;
}

/** Clean a whole block value `{content, settings}` using its described fields. */
export function cleanBlock(
  fields: ReturnType<typeof describeBlock>,
  value: { content: Record<string, unknown>; settings: Record<string, unknown> },
) {
  const obj = (f: Record<string, FieldSpec>): FieldSpec => ({ kind: "object", optional: false, fields: f });
  return {
    content: (cleanValue(value.content, obj(fields.content)) ?? {}) as Record<string, unknown>,
    settings: (cleanValue(value.settings, obj(fields.settings)) ?? {}) as Record<string, unknown>,
  };
}
