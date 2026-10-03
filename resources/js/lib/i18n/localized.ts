import { z } from "zod";
import { defaultLocale, locales, type Locale } from "./config";

/**
 * LocalizedString — used for SHORT labels on supporting records (alt text, captions,
 * navigation labels, settings text, form messages). See ADR-007.
 * Italian is required (default locale); other locales are optional and fall back to Italian.
 */
export const localizedString = z.object({
  it: z.string().trim().min(1).max(2000),
  en: z.string().trim().max(2000).optional(),
});
export type LocalizedString = z.infer<typeof localizedString>;

/** Same as localizedString but all locales optional (for optional fields). */
export const optionalLocalizedString = z.object({
  it: z.string().trim().max(2000).optional(),
  en: z.string().trim().max(2000).optional(),
});
export type OptionalLocalizedString = z.infer<typeof optionalLocalizedString>;

/**
 * Pick the value for `locale`, falling back to the default locale, then to any value.
 * Accepts unknown JSON (straight from Prisma) and never throws.
 */
export function pickLocalized(value: unknown, locale: Locale): string {
  if (!value || typeof value !== "object") return typeof value === "string" ? value : "";
  const record = value as Partial<Record<Locale, unknown>>;
  const direct = record[locale];
  if (typeof direct === "string" && direct.trim()) return direct;
  const fallback = record[defaultLocale];
  if (typeof fallback === "string" && fallback.trim()) return fallback;
  for (const l of locales) {
    const v = record[l];
    if (typeof v === "string" && v.trim()) return v;
  }
  return "";
}

/** Helper for seed/tests: build a LocalizedString. */
export function ls(it: string, en?: string): LocalizedString {
  return en ? { it, en } : { it };
}
