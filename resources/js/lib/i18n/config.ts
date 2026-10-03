/**
 * Locale configuration shared by routing, dictionaries, content queries and the admin.
 * Client-safe (no server imports).
 */
export const locales = ["it", "en"] as const;
export type Locale = (typeof locales)[number];
export const defaultLocale: Locale = "it";

export const localeLabels: Record<Locale, string> = {
  it: "Italiano",
  en: "English",
};

export function isLocale(value: unknown): value is Locale {
  return typeof value === "string" && (locales as readonly string[]).includes(value);
}
