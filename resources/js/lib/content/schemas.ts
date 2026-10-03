import { z } from "zod";
import { isValidVideoId } from "@/lib/richtext/video";
import {
  bodySchema,
  httpsUrlOrEmpty,
  isoDateOrEmpty,
  optionalId,
  seoShape,
  text,
  translations,
} from "./common";

/**
 * Form/input schemas for each content collection. Client-safe; shared by admin forms
 * (zodResolver) and server actions (which re-parse everything).
 */

// Money fields are typed as major units ("1500" or "1500.50") and stored as minor units.
const moneyOrEmpty = z
  .string()
  .trim()
  .max(15)
  .refine((v) => v === "" || /^\d{1,10}(\.\d{1,2})?$/.test(v), "Enter an amount like 1500 or 1500.50");

const coordOrEmpty = (max: number) =>
  z
    .string()
    .trim()
    .max(20)
    .refine(
      (v) => v === "" || (Number.isFinite(Number(v)) && Math.abs(Number(v)) <= max),
      `Between -${max} and ${max}`,
    );

const position = z.coerce.number().int().min(0).max(100000);

// ── News & stories ─────────────────────────────────────────────────
export const newsFormSchema = z.object({
  publishedAt: isoDateOrEmpty,
  coverImageId: optionalId,
  categoryId: optionalId,
  projectId: optionalId,
  authorId: optionalId,
  isFeatured: z.boolean(),
  tags: z.array(z.string().trim().min(1).max(40)).max(10),
  translations: translations({ excerpt: text(300), body: bodySchema, ...seoShape }),
});
export type NewsFormValues = z.infer<typeof newsFormSchema>;

// ── Projects ───────────────────────────────────────────────────────
export const projectStages = ["PLANNED", "ACTIVE", "COMPLETED"] as const;
export const currencies = ["EUR", "NPR", "USD"] as const;

export const projectFormSchema = z.object({
  stage: z.enum(projectStages),
  district: text(100),
  latitude: coordOrEmpty(90),
  longitude: coordOrEmpty(180),
  startDate: isoDateOrEmpty,
  endDate: isoDateOrEmpty,
  goalAmount: moneyOrEmpty,
  raisedAmount: moneyOrEmpty,
  currency: z.enum(currencies),
  coverImageId: optionalId,
  isFeatured: z.boolean(),
  position,
  publishedAt: isoDateOrEmpty,
  translations: translations({
    sector: text(60),
    metaLine: text(120),
    summary: text(400),
    body: bodySchema,
    ...seoShape,
  }),
});
export type ProjectFormValues = z.infer<typeof projectFormSchema>;

// ── Events ─────────────────────────────────────────────────────────
export const eventTimezones = ["Europe/Rome", "Asia/Kathmandu"] as const;

export const eventFormSchema = z
  .object({
    startsAt: z.iso.datetime({ offset: true, error: "Start date is required" }),
    endsAt: isoDateOrEmpty,
    allDay: z.boolean(),
    timezone: z.enum(eventTimezones),
    address: text(200),
    mapUrl: httpsUrlOrEmpty,
    onlineUrl: httpsUrlOrEmpty,
    registrationUrl: httpsUrlOrEmpty,
    coverImageId: optionalId,
    isFeatured: z.boolean(),
    publishedAt: isoDateOrEmpty,
    translations: translations({
      summary: text(400),
      locationName: text(120),
      body: bodySchema,
      ...seoShape,
    }),
  })
  .superRefine((v, ctx) => {
    if (v.endsAt && new Date(v.endsAt) < new Date(v.startsAt)) {
      ctx.addIssue({ code: "custom", path: ["endsAt"], message: "The end must be after the start" });
    }
  });
export type EventFormValues = z.infer<typeof eventFormSchema>;

// ── Gallery albums ─────────────────────────────────────────────────
export const galleryItemSchema = z
  .object({
    key: z.string().max(64), // client-side key for ordering (existing id or random)
    type: z.enum(["PHOTO", "VIDEO"]),
    mediaAssetId: optionalId,
    videoProvider: z.enum(["youtube", "vimeo"]).nullable(),
    videoId: z.string().trim().max(20).nullable(),
    captionIt: text(300),
    captionEn: text(300),
    // Display-only (not persisted): thumbnail for the editor.
    previewUrl: z.string().max(1000).nullable().optional(),
  })
  .superRefine((v, ctx) => {
    if (v.type === "PHOTO" && !v.mediaAssetId)
      ctx.addIssue({ code: "custom", path: ["mediaAssetId"], message: "Choose a photo" });
    if (v.type === "VIDEO" && !isValidVideoId(v.videoProvider, v.videoId))
      ctx.addIssue({ code: "custom", path: ["videoId"], message: "Invalid YouTube/Vimeo video" });
  });
export type GalleryItemValues = z.infer<typeof galleryItemSchema>;

export const albumFormSchema = z.object({
  coverImageId: optionalId,
  projectId: optionalId,
  takenAt: isoDateOrEmpty,
  position,
  publishedAt: isoDateOrEmpty,
  items: z.array(galleryItemSchema).max(300),
  translations: translations({ description: text(1000) }),
});
export type AlbumFormValues = z.infer<typeof albumFormSchema>;

// ── Transparency reports ───────────────────────────────────────────
export const reportTypes = ["ANNUAL_REPORT", "FINANCIAL_STATEMENT", "SOCIAL_REPORT", "OTHER"] as const;
export const reportTypeLabels: Record<(typeof reportTypes)[number], string> = {
  ANNUAL_REPORT: "Annual report",
  FINANCIAL_STATEMENT: "Financial statement",
  SOCIAL_REPORT: "Social report",
  OTHER: "Other",
};

const reportTr = z.object({ title: text(200), description: text(1000) });

export const reportFormSchema = z.object({
  type: z.enum(reportTypes),
  year: z.coerce.number().int().min(1990).max(2100),
  fileId: optionalId,
  coverImageId: optionalId,
  position,
  publishedAt: isoDateOrEmpty,
  translations: z.object({
    it: reportTr.superRefine((v, ctx) => {
      if (!v.title) ctx.addIssue({ code: "custom", path: ["title"], message: "Title is required" });
    }),
    en: reportTr.extend({ enabled: z.boolean(), fileId: optionalId }).superRefine((v, ctx) => {
      if (v.enabled && !v.title)
        ctx.addIssue({ code: "custom", path: ["title"], message: "Title is required" });
    }),
  }),
});
export type ReportFormValues = z.infer<typeof reportFormSchema>;

/** Major-unit string → integer minor units (null when empty). */
export function toMinorUnits(v: string): number | null {
  if (!v) return null;
  const [int, dec = ""] = v.split(".");
  return Number(int) * 100 + Number((dec + "00").slice(0, 2));
}
export function fromMinorUnits(v: number | null | undefined): string {
  if (v === null || v === undefined) return "";
  return v % 100 === 0 ? String(v / 100) : (v / 100).toFixed(2);
}

// ── Media metadata ─────────────────────────────────────────────────
const focal = z.number().min(0).max(1).nullable();
export const mediaMetaSchema = z.object({
  altIt: z.string().trim().max(300),
  altEn: z.string().trim().max(300),
  captionIt: z.string().trim().max(300),
  captionEn: z.string().trim().max(300),
  credit: z.string().trim().max(200),
  focalX: focal,
  focalY: focal,
});
export type MediaMetaValues = z.infer<typeof mediaMetaSchema>;
