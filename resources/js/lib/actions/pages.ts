/**
 * Page-builder actions (reference: lib/actions/pages.ts). Same exported names and result shapes;
 * the logic runs in Laravel (App\Pages\PageActions via routes/admin/pages.php).
 */
import type { z } from "zod";
import type { Locale } from "@/lib/i18n/config";
import type { pageMetaSchema, saveSectionsSchema } from "@/lib/pages/schemas";
import type { SectionIssue } from "@/lib/pages/publish";
import { callAction } from "./client";

export type SaveDraftInput = z.input<typeof saveSectionsSchema> & {
  meta: Omit<z.input<typeof pageMetaSchema>, "pageId" | "locale">;
};

export type SaveDraftResult = {
  /** New concurrency token for the next save. */
  version: string;
  /** Persisted section ids in the same order as the submitted sections. */
  sectionIds: string[];
  /** Problems that will block publishing (the draft is still saved). */
  issues: SectionIssue[];
};

export const createPageAction = (input: unknown) => callAction<{ id: string }>("pages/create", input);

/** Creates a missing system page (home, privacy …) as an empty draft; see PagesActionsController::createSystem. */
export const createSystemPageAction = (input: { key: string }) =>
  callAction<{ id: string }>("pages/create-system", input);

export const createPageTranslationAction = (input: unknown) =>
  callAction<{ locale: Locale }>("pages/create-translation", input);

export const stagePreviewDraftAction = (input: unknown) =>
  callAction<{ stagedAt: string }>("pages/stage-preview", input);

export const savePageDraftAction = (input: unknown) => callAction<SaveDraftResult>("pages/save-draft", input);

export const publishPageAction = (input: unknown) =>
  callAction<{ publishedAt: string; revisionId: string }>("pages/publish", input);

export const unpublishPageAction = (input: unknown) => callAction<null>("pages/unpublish", input);

export const restoreRevisionAction = (input: unknown) => callAction<null>("pages/restore", input);

export const copySectionsAction = (input: unknown) =>
  callAction<{ count: number }>("pages/copy-sections", input);

export const deletePageAction = (input: unknown) => callAction<null>("pages/delete", input);
