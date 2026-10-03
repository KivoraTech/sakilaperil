/**
 * Cache tags for public content (unstable_cache in lib/queries/public/**).
 * After ANY admin mutation that affects public output, call
 * `revalidateTag(tag, "max")` for the affected tags (see docs/architecture.md §Caching).
 */
export const cacheTags = {
  settings: "settings",
  navigation: "navigation",
  pages: "pages",
  page: (id: string) => `page:${id}`,
  projects: "projects",
  project: (id: string) => `project:${id}`,
  news: "news",
  newsPost: (id: string) => `news:${id}`,
  events: "events",
  event: (id: string) => `event:${id}`,
  albums: "albums",
  album: (id: string) => `album:${id}`,
  reports: "reports",
  media: "media",
} as const;

/** Default time-based safety net for cached public reads (seconds). */
export const PUBLIC_REVALIDATE_SECONDS = 3600;
