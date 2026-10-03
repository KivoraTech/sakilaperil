/** Client-safe media DTOs shared by the media library, MediaPicker and server code. */
export type MediaKindValue = "IMAGE" | "VIDEO" | "DOCUMENT";

export type MediaAssetDTO = {
  id: string;
  kind: MediaKindValue;
  url: string;
  filename: string;
  mimeType: string;
  size: number;
  width: number | null;
  height: number | null;
  alt: { it: string; en: string };
  caption: { it: string; en: string };
  credit: string;
  focalX: number | null;
  focalY: number | null;
  storageDriver: string;
  uploadedBy: string | null;
  createdAt: string;
};

export type MediaUsage = {
  kind: "news" | "project" | "event" | "album" | "report" | "campaign" | "page" | "settings";
  label: string;
  /** Admin edit link, when there is one. */
  href: string | null;
  /** True when the reference is live on the public site (deleting would break it). */
  published: boolean;
  /** Human description of the field, e.g. "Cover image". */
  where: string;
};

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
