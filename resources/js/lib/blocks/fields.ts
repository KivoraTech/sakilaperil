import { z } from "zod";

/**
 * Reusable field schemas for block content. Pure/client-safe (used by admin forms,
 * server validation and the public renderer).
 */

/** Only safe link targets: site-relative paths, anchors, https, mailto, tel. No javascript:/data:. */
export const SAFE_HREF =
  // "/\evil.com" is treated as protocol-relative ("//evil.com") by browsers, so a leading
  // slash may be followed by neither "/" nor "\"; backslashes are rejected everywhere.
  /^(\/(?![/\\])[^\s\\]*|#[A-Za-z][\w-]*|https:\/\/[^\s]+|mailto:[^\s]+|tel:\+?[\d\s()-]+)$/;

export const safeHref = z
  .string()
  .trim()
  .min(1)
  .max(500)
  .regex(SAFE_HREF, "Use a path starting with /, an #anchor, an https:// URL, mailto: or tel:");

export const link = z.object({
  label: z.string().trim().min(1).max(80),
  href: safeHref,
});
export type BlockLink = z.infer<typeof link>;

/** Reference to a MediaAsset; `alt` overrides the asset's own alt text for this placement. */
export const imageRef = z.object({
  assetId: z.string().min(1).max(64),
  alt: z.string().trim().max(300).optional(),
});
export type ImageRef = z.infer<typeof imageRef>;

export const shortText = (max = 160) => z.string().trim().max(max);
export const requiredText = (max = 160) => z.string().trim().min(1).max(max);
export const plainText = (max = 4000) => z.string().trim().max(max);
export const entityId = z.string().min(1).max(64);

/** Optional in-page anchor so links like "#dona" can target a section. */
export const anchor = z
  .string()
  .trim()
  .regex(/^[a-z][a-z0-9-]{0,40}$/, "Lowercase letters, numbers and dashes")
  .optional();

export const background = z.enum(["paper", "cream", "white", "himalaya", "deep"]).default("paper");

/** Settings every block supports. */
export const baseSettings = z.object({
  anchor,
  background,
});
