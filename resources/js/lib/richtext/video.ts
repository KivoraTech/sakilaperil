/**
 * YouTube / Vimeo helpers shared by the rich-text editor, the safe renderer and the gallery.
 * Videos are never hosted (ADR-004): we store only { provider, id } validated by regex and
 * build the embed URL ourselves, so no user-supplied URL or HTML ever reaches an iframe.
 * Pure and client-safe.
 */
export type VideoProviderKey = "youtube" | "vimeo";

export const VIDEO_ID_PATTERNS: Record<VideoProviderKey, RegExp> = {
  youtube: /^[A-Za-z0-9_-]{11}$/,
  vimeo: /^\d{1,12}$/,
};

export function isValidVideoId(provider: unknown, id: unknown): provider is VideoProviderKey {
  return (
    (provider === "youtube" || provider === "vimeo") &&
    typeof id === "string" &&
    VIDEO_ID_PATTERNS[provider].test(id)
  );
}

const YOUTUBE_HOSTS = new Set([
  "youtube.com",
  "www.youtube.com",
  "m.youtube.com",
  "music.youtube.com",
  "youtube-nocookie.com",
  "www.youtube-nocookie.com",
]);
const VIMEO_HOSTS = new Set(["vimeo.com", "www.vimeo.com", "player.vimeo.com"]);

/**
 * Parse a pasted YouTube/Vimeo URL (watch, short, embed, shorts, player) into a validated
 * { provider, id }. Returns null for anything else.
 */
export function parseVideoUrl(input: string): { provider: VideoProviderKey; id: string } | null {
  let url: URL;
  try {
    url = new URL(input.trim());
  } catch {
    return null;
  }
  if (url.protocol !== "https:" && url.protocol !== "http:") return null;
  const host = url.hostname.toLowerCase();
  const parts = url.pathname.split("/").filter(Boolean);

  let provider: VideoProviderKey | null = null;
  let id: string | undefined;
  if (host === "youtu.be") {
    provider = "youtube";
    id = parts[0];
  } else if (YOUTUBE_HOSTS.has(host)) {
    provider = "youtube";
    if (parts[0] === "watch") id = url.searchParams.get("v") ?? undefined;
    else if (["embed", "shorts", "live", "v"].includes(parts[0] ?? "")) id = parts[1];
  } else if (VIMEO_HOSTS.has(host)) {
    provider = "vimeo";
    id = host === "player.vimeo.com" && parts[0] === "video" ? parts[1] : parts.find((p) => /^\d+$/.test(p));
  }
  if (!provider || !id || !isValidVideoId(provider, id)) return null;
  return { provider, id };
}

/** Privacy-friendly embed URL (youtube-nocookie / Vimeo do-not-track). */
export function videoEmbedUrl(provider: VideoProviderKey, id: string): string {
  if (!isValidVideoId(provider, id)) throw new Error("Invalid video id");
  return provider === "youtube"
    ? `https://www.youtube-nocookie.com/embed/${id}?rel=0`
    : `https://player.vimeo.com/video/${id}?dnt=1`;
}

/** Canonical watch URL (for "open on YouTube/Vimeo" links). */
export function videoWatchUrl(provider: VideoProviderKey, id: string): string {
  return provider === "youtube" ? `https://www.youtube.com/watch?v=${id}` : `https://vimeo.com/${id}`;
}

/** Static thumbnail (YouTube only; Vimeo needs an API call, so callers show a placeholder). */
export function videoThumbnailUrl(provider: VideoProviderKey, id: string): string | null {
  return provider === "youtube" && isValidVideoId(provider, id)
    ? `https://i.ytimg.com/vi/${id}/hqdefault.jpg`
    : null;
}
