import { z } from "zod";

/** Shape of PageRevision.snapshot (written by the builder's publish action). */
export const pageSnapshotSchema = z.object({
  title: z.string(),
  seoTitle: z.string().nullish(),
  seoDescription: z.string().nullish(),
  ogImageId: z.string().nullish(),
  noIndex: z.boolean().default(false),
  sections: z.array(
    z.object({
      id: z.string(),
      type: z.string(),
      schemaVersion: z.number().int(),
      isVisible: z.boolean().default(true),
      content: z.unknown(),
      settings: z.unknown().default({}),
    }),
  ),
});
export type PageSnapshot = z.infer<typeof pageSnapshotSchema>;

/**
 * Build the immutable PageRevision.snapshot for one page+locale from its draft rows.
 * Used by the seed and by the publish action (which must also validate
 * every section with validateBlockInput() before calling this).
 */
export function buildPageSnapshot(input: {
  title: string;
  seoTitle?: string | null;
  seoDescription?: string | null;
  ogImageId?: string | null;
  noIndex?: boolean;
  sections: {
    id: string;
    type: string;
    schemaVersion: number;
    isVisible: boolean;
    position: number;
    content: unknown;
    settings: unknown;
  }[];
}): PageSnapshot {
  return {
    title: input.title,
    seoTitle: input.seoTitle ?? null,
    seoDescription: input.seoDescription ?? null,
    ogImageId: input.ogImageId ?? null,
    noIndex: input.noIndex ?? false,
    sections: [...input.sections]
      .sort((a, b) => a.position - b.position)
      .filter((s) => s.isVisible)
      .map(({ id, type, schemaVersion, isVisible, content, settings }) => ({
        id,
        type,
        schemaVersion,
        isVisible,
        content,
        settings: settings ?? {},
      })),
  };
}
