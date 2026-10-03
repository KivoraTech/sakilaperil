import type { ContentStatusValue, SaveIntent } from "@/lib/content/common";
import type { NewsFormValues } from "@/lib/content/schemas";
import { callAction } from "./client";

/**
 * Port of sakila/lib/actions/news.ts: same exported names and signatures; the work happens in
 * App\Http\Controllers\Admin\NewsController (POST /admin/actions/news/*).
 */
export const saveNewsAction = (id: string | null, input: NewsFormValues, intent: SaveIntent = "save") =>
  callAction<{ id: string }>("news/save", { id, input, intent });

export const setNewsStatusAction = (id: string, status: ContentStatusValue) =>
  callAction<{ id: string }>("news/set-status", { id, status });

export const deleteNewsAction = (id: string) => callAction<{ id: string }>("news/delete", { id });

/** Create a news category (editors+). Returns the new id for the form's select. */
export const createNewsCategoryAction = (input: { nameIt: string; nameEn: string }) =>
  callAction<{ id: string; name: string }>("news/create-category", { input });
