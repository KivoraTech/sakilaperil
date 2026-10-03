import type { ContentStatusValue, SaveIntent } from "@/lib/content/common";
import type { AlbumFormValues } from "@/lib/content/schemas";
import { callAction } from "./client";

/**
 * Port of sakila/lib/actions/gallery.ts: same exported names and signatures; the work happens in
 * App\Http\Controllers\Admin\GalleryController (POST /admin/actions/gallery/*).
 */
export const saveAlbumAction = (id: string | null, input: AlbumFormValues, intent: SaveIntent = "save") =>
  callAction<{ id: string }>("gallery/save", { id, input, intent });

export const setAlbumStatusAction = (id: string, status: ContentStatusValue) =>
  callAction<{ id: string }>("gallery/set-status", { id, status });

export const deleteAlbumAction = (id: string) => callAction<{ id: string }>("gallery/delete", { id });
