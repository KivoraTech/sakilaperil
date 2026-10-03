/**
 * URL conventions (ADR-007). Client-safe.
 * - Every public URL is locale-prefixed: /it/..., /en/...
 * - Collection segments are the same English words in both locales; item slugs are localized:
 *   /it/projects/una-scuola-per-il-villaggio  ·  /en/projects/a-school-for-the-village
 * - Pages (built with blocks) live at /{locale}/{page-slug} with localized slugs
 *   (/it/chi-siamo, /en/about-us). The home page has slug "home" and is served at /{locale}.
 * - Stored internal links (blocks, navigation) are LOCALE-LESS ("/projects", "/chi-siamo#team")
 *   and rendered with the Link from @/i18n/navigation, which adds the prefix.
 */
export const collectionPaths = {
  projects: "/projects",
  news: "/news",
  events: "/events",
  gallery: "/gallery",
} as const;
export type CollectionKey = keyof typeof collectionPaths;

/** System pages the code links to by key (Page.key). */
export const systemPageKeys = [
  "home",
  "about",
  "get-involved",
  "transparency",
  "contact",
  "privacy",
  "cookies",
] as const;
export type SystemPageKey = (typeof systemPageKeys)[number];

/** Built-in route keys usable in navigation (NavigationItem.routeKey). */
export const routeKeys = [...Object.keys(collectionPaths), ...systemPageKeys, "donate"] as ReadonlyArray<
  CollectionKey | SystemPageKey | "donate"
>;
export type RouteKey = (typeof routeKeys)[number];

/** Page slugs that would be shadowed by app routes. The page builder must reject them. */
export const RESERVED_SLUGS = new Set([
  ...Object.keys(collectionPaths),
  "admin",
  "api",
  "preview",
  "uploads",
  "donate",
  "sitemap.xml",
  "robots.txt",
  "_next",
]);

export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export function slugify(input: string): string {
  return input
    .normalize("NFKD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
}

export const pathFor = {
  home: () => "/",
  page: (slug: string) => (slug === "home" ? "/" : `/${slug}`),
  project: (slug: string) => `${collectionPaths.projects}/${slug}`,
  news: (slug: string) => `${collectionPaths.news}/${slug}`,
  event: (slug: string) => `${collectionPaths.events}/${slug}`,
  album: (slug: string) => `${collectionPaths.gallery}/${slug}`,
};
