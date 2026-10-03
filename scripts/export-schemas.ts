/**
 * Exports the Zod schemas shared with the admin (resources/js/lib/**) to JSON Schema so PHP
 * validates with exactly the same rules (ADR-012). Run by `npm run build` (and `npm run schemas`);
 * the output in resources/schemas/ is committed so the server never needs Node.
 *
 * - Every exported Zod schema of each module → resources/schemas/<module>.json { name: JSONSchema }
 * - Block registry → resources/schemas/blocks.json { types: { <type>: { label, description, category,
 *   version, singleton, defaults, content, settings } } }
 * Schemas use the INPUT shape (fields with defaults are optional); PHP applies `default` values
 * (Opis defaultSupport) and trims/lowercases the strings marked `x-trim`/`x-lowercase`, mirroring
 * Zod's .trim()/.toLowerCase()/.default().
 */
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { z } from "zod";
import * as forms from "../resources/js/lib/forms/schemas";
import * as localized from "../resources/js/lib/i18n/localized";
import * as navigation from "../resources/js/lib/navigation/schema";
import * as pages from "../resources/js/lib/pages/schemas";
import * as richtext from "../resources/js/lib/richtext/schema";
import * as settings from "../resources/js/lib/settings/schema";
import * as settingsSections from "../resources/js/lib/settings/sections";
import { blockRegistry } from "../resources/js/lib/blocks/registry";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const out = resolve(root, "resources/schemas");
mkdirSync(out, { recursive: true });

/**
 * Zod's `.trim()` / `.toLowerCase()` are "overwrite" checks that JSON Schema cannot express. Mark them
 * as `x-trim` / `x-lowercase` so PHP normalises EXACTLY the strings Zod normalises (rich-text `text`
 * nodes, for example, must keep their spaces: "a " + bold "b").
 */
type OverwriteCheck = { _zod: { def: { check?: string; tx?: (v: string) => string } } };
function markStringTransforms(ctx: { zodSchema: unknown; jsonSchema: Record<string, unknown> }) {
  const def = (ctx.zodSchema as { _zod?: { def?: { type?: string; checks?: OverwriteCheck[] } } })._zod?.def;
  if (def?.type !== "string") return;
  for (const check of def.checks ?? []) {
    const { check: kind, tx } = check._zod.def;
    if (kind !== "overwrite" || !tx) continue;
    const out = tx(" Ab ");
    if (out === "Ab") ctx.jsonSchema["x-trim"] = true;
    else if (out === " ab ") ctx.jsonSchema["x-lowercase"] = true;
  }
}

const toJson = (schema: z.ZodType) =>
  z.toJSONSchema(schema, {
    io: "input",
    unrepresentable: "any",
    target: "draft-2020-12",
    override: markStringTransforms,
  });

function exportModule(name: string, mod: Record<string, unknown>) {
  const result: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(mod)) {
    if (value instanceof z.ZodType) result[key] = toJson(value);
    else if (value && typeof value === "object" && !Array.isArray(value)) {
      // Records of schemas (e.g. settingsSectionSchemas, formSchemas): export each entry.
      const entries = Object.entries(value as Record<string, unknown>).filter(
        ([, v]) => v instanceof z.ZodType,
      );
      if (entries.length)
        result[key] = Object.fromEntries(entries.map(([k, v]) => [k, toJson(v as z.ZodType)]));
    }
  }
  writeFileSync(resolve(out, `${name}.json`), `${JSON.stringify(result, null, 2)}\n`);
  return Object.keys(result).length;
}

const counts = {
  forms: exportModule("forms", forms),
  localized: exportModule("localized", localized),
  navigation: exportModule("navigation", navigation),
  pages: exportModule("pages", pages),
  richtext: exportModule("richtext", richtext),
  settings: exportModule("settings", settings),
  "settings-sections": exportModule("settings-sections", settingsSections),
};

const types: Record<string, unknown> = {};
for (const [type, def] of Object.entries(blockRegistry)) {
  types[type] = {
    label: def.label,
    description: def.description,
    category: def.category,
    version: def.version,
    singleton: def.singleton ?? false,
    hasMigrations: typeof def.migrate === "function",
    defaults: def.defaults(),
    content: toJson(def.content),
    settings: toJson(def.settings),
  };
}
writeFileSync(resolve(out, "blocks.json"), `${JSON.stringify({ types }, null, 2)}\n`);

console.log(`schemas: ${Object.keys(types).length} block types,`, counts);
