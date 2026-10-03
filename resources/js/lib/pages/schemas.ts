import { z } from "zod";
import { blockTypes } from "@/lib/blocks/registry";
import { locales } from "@/lib/i18n/config";
import { RESERVED_SLUGS, SLUG_PATTERN } from "@/lib/i18n/routes";

/**
 * Input schemas for the page-builder server actions (lib/actions/pages.ts).
 * Pure and client-safe: the admin forms reuse them with React Hook Form.
 */

export const localeSchema = z.enum(locales);
export const idSchema = z.string().min(1).max(64);

/** The home page keeps slug "home" (served at /{locale}); no other page may use it. */
export const HOME_SLUG = "home";

export const slugSchema = z
  .string()
  .trim()
  .toLowerCase()
  .min(1, "Enter a slug")
  .max(80, "Keep the slug under 80 characters")
  .regex(SLUG_PATTERN, "Use lowercase letters, numbers and single dashes (e.g. chi-siamo)")
  .refine((s) => !RESERVED_SLUGS.has(s), "This slug is reserved by the website")
  .refine((s) => s !== HOME_SLUG, "“home” is reserved for the home page");

const optionalText = (max: number) =>
  z
    .string()
    .trim()
    .max(max)
    .optional()
    .transform((v) => (v ? v : null));

export const pageTitleSchema = z.string().trim().min(1, "Enter a title").max(160);

export const createPageSchema = z
  .object({
    title: pageTitleSchema,
    slug: slugSchema,
    /** Optional English version created at the same time. */
    enTitle: z.string().trim().max(160).optional(),
    enSlug: z.string().trim().toLowerCase().max(80).optional(),
  })
  .superRefine((v, ctx) => {
    const hasEn = !!v.enTitle || !!v.enSlug;
    if (!hasEn) return;
    if (!v.enTitle) ctx.addIssue({ code: "custom", path: ["enTitle"], message: "Enter the English title" });
    const slug = slugSchema.safeParse(v.enSlug ?? "");
    if (!slug.success)
      ctx.addIssue({ code: "custom", path: ["enSlug"], message: slug.error.issues[0]?.message ?? "Invalid" });
  });
export type CreatePageInput = z.input<typeof createPageSchema>;

export const createTranslationSchema = z.object({
  pageId: idSchema,
  locale: localeSchema,
  title: pageTitleSchema,
  slug: slugSchema,
  /** Copy the draft sections of this locale into the new translation. */
  copyFrom: localeSchema.optional(),
});
export type CreateTranslationInput = z.input<typeof createTranslationSchema>;

export const pageMetaSchema = z.object({
  pageId: idSchema,
  locale: localeSchema,
  title: pageTitleSchema,
  /** Ignored for the home page (always "home"). */
  slug: z.string().trim().toLowerCase().max(80),
  seoTitle: optionalText(70),
  seoDescription: optionalText(160),
  ogImageId: optionalText(64),
  noIndex: z.boolean().default(false),
});
export type PageMetaInput = z.input<typeof pageMetaSchema>;

/** Hard cap on one section's JSON so a malicious client cannot store megabytes per row. */
export const MAX_SECTION_BYTES = 100_000;
export const MAX_SECTIONS = 60;

const jsonObject = z
  .record(z.string(), z.unknown())
  .refine((v) => JSON.stringify(v).length <= MAX_SECTION_BYTES, "This section is too large");

export const draftSectionSchema = z.object({
  /** Existing PageSection id, or absent for a section added in the builder. */
  id: idSchema.optional(),
  type: z.enum(blockTypes as [string, ...string[]]),
  isVisible: z.boolean(),
  content: jsonObject,
  settings: jsonObject.default({}),
});
export type DraftSectionInput = z.input<typeof draftSectionSchema>;

export const saveSectionsSchema = z.object({
  pageId: idSchema,
  locale: localeSchema,
  sections: z.array(draftSectionSchema).max(MAX_SECTIONS, `A page can have at most ${MAX_SECTIONS} sections`),
  /**
   * Optimistic concurrency token: the draft's `updatedAt` the editor loaded. If someone else saved
   * in between, the save is rejected with CONFLICT instead of silently overwriting their work.
   */
  expectedVersion: z.string().max(40).optional(),
});
export type SaveSectionsInput = z.input<typeof saveSectionsSchema>;

export const pageLocaleSchema = z.object({ pageId: idSchema, locale: localeSchema });
export type PageLocaleInput = z.input<typeof pageLocaleSchema>;

/** Publishing a page with no visible section needs an explicit confirmation (never allowed for the home page). */
export const publishPageSchema = pageLocaleSchema.extend({ confirmEmpty: z.boolean().optional() });

export const deletePageSchema = z.object({ pageId: idSchema });

export const listPagesSchema = z.object({
  q: z.string().trim().max(100).optional().catch(undefined),
  status: z.enum(["all", "published", "draft", "changes"]).catch("all").default("all"),
  page: z.coerce.number().int().min(1).max(10_000).catch(1).default(1),
});
export type ListPagesParams = z.infer<typeof listPagesSchema>;

/** Random id of one editor tab; ties the iframe preview to the draft that tab staged. */
export const previewClientIdSchema = z.string().regex(/^[A-Za-z0-9_-]{8,64}$/, "Invalid preview id");

/** Unsaved editor state sent to the live side-by-side preview (never persisted as content). */
export const stagePreviewSchema = z.object({
  pageId: idSchema,
  locale: localeSchema,
  clientId: previewClientIdSchema,
  title: z.string().trim().max(160).default(""),
  sections: z
    .array(
      draftSectionSchema.extend({
        /** Builder key (the saved id, or `new-…` for unsaved sections): rendered as `data-section-id`. */
        key: z.string().min(1).max(80),
      }),
    )
    .max(MAX_SECTIONS, `A page can have at most ${MAX_SECTIONS} sections`),
});
export type StagePreviewInput = z.input<typeof stagePreviewSchema>;

/** Staged previews are ignored (and cleaned up) after this long. */
export const PREVIEW_DRAFT_TTL_MS = 24 * 60 * 60 * 1000;
