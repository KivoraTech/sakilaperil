import { z } from "zod";
import { SAFE_HREF } from "@/lib/blocks/fields";
import { SLUG_PATTERN } from "@/lib/i18n/routes";
import { richTextDocSchema } from "@/lib/richtext/schema";

/**
 * Shared, CLIENT-SAFE form schemas for the content collections (news, projects, events,
 * gallery, reports). The same schema validates the React Hook Form on the client and the
 * server action input (server actions re-parse; rich text is additionally sanitised).
 * Empty strings mean "not set" and are normalised to null by `blank()` on the server.
 */
export const contentStatuses = ["DRAFT", "PUBLISHED", "ARCHIVED"] as const;
export type ContentStatusValue = (typeof contentStatuses)[number];
export const contentStatusSchema = z.enum(contentStatuses);

/** "save" keeps the current status (new items start as DRAFT); "publish" sets PUBLISHED. */
export const saveIntentSchema = z.enum(["save", "publish"]);
export type SaveIntent = z.infer<typeof saveIntentSchema>;

export const idSchema = z.string().trim().min(1).max(64);
export const optionalId = z.string().trim().max(64).nullable();

export const text = (max: number) => z.string().trim().max(max);

/** ISO datetime or "" (unset). Forms convert datetime-local values to ISO before submitting. */
export const isoDateOrEmpty = z.union([z.literal(""), z.iso.datetime({ offset: true })]);

export const httpsUrlOrEmpty = z
  .string()
  .trim()
  .max(500)
  .refine((v) => v === "" || /^https:\/\/[^\s]+$/.test(v), "Use a full https:// link");

export const safeHrefOrEmpty = z
  .string()
  .trim()
  .max(500)
  .refine((v) => v === "" || SAFE_HREF.test(v), "Use https://…, /path, mailto: or tel:");

export const bodySchema = richTextDocSchema;

/** Title + slug required (Italian always; English only when enabled). */
function requireTitleSlug(v: { title: string; slug: string }, ctx: z.RefinementCtx) {
  if (!v.title) ctx.addIssue({ code: "custom", path: ["title"], message: "Title is required" });
  if (!v.slug) ctx.addIssue({ code: "custom", path: ["slug"], message: "Slug is required" });
  else if (!SLUG_PATTERN.test(v.slug))
    ctx.addIssue({ code: "custom", path: ["slug"], message: "Lowercase letters, numbers and dashes only" });
}

/**
 * Build `{ it, en }` translation schemas from a field shape. Italian is required; English is
 * optional (`enabled` toggles it) and falls back to Italian publicly (ADR-007).
 */
export function translations<S extends z.ZodRawShape>(shape: S) {
  const base = z.object({ title: text(200), slug: text(80), ...shape });
  return z.object({
    it: base.superRefine((v, ctx) => requireTitleSlug(v as { title: string; slug: string }, ctx)),
    en: base.extend({ enabled: z.boolean() }).superRefine((v, ctx) => {
      if ((v as { enabled: boolean }).enabled) requireTitleSlug(v as { title: string; slug: string }, ctx);
    }),
  });
}

export const seoShape = {
  seoTitle: text(70),
  seoDescription: text(170),
};

/** "" → null (server-side normalisation). */
export const blank = <T extends string | null | undefined>(v: T): Exclude<T, ""> | null =>
  v === "" || v === undefined || v === null ? null : (v as Exclude<T, "">);

export const dateOrNull = (v: string | null | undefined): Date | null => (v ? new Date(v) : null);

export const PAGE_SIZE = 20;

/** List-page search params (validated; bad values fall back to defaults). */
export const listParamsSchema = z.object({
  q: z.string().trim().max(100).catch(""),
  status: z.union([contentStatusSchema, z.literal("")]).catch(""),
  page: z.coerce.number().int().min(1).max(10000).catch(1),
});
export type ListParams = z.infer<typeof listParamsSchema>;

export function parseListParams(sp: Record<string, string | string[] | undefined>): ListParams {
  const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
  return listParamsSchema.parse({
    q: one(sp.q) ?? "",
    status: one(sp.status) ?? "",
    page: one(sp.page) ?? 1,
  });
}
