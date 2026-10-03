import type { MediaAssetDTO, MediaUsage } from "@/lib/content/media";
import type { MediaMetaValues } from "@/lib/content/schemas";
import { callAction } from "./client";

/**
 * Port of sakila/lib/actions/media.ts (same names/signatures) → App\Http\Controllers\Admin\MediaController
 * (POST /admin/actions/media/*). Uploads go to POST /api/media (MediaUploader).
 */
export type MediaSearchResult = {
  items: MediaAssetDTO[];
  total: number;
  page: number;
  pageSize: number;
  pageCount: number;
};

/** Paged search for the MediaPicker dialog. */
export const searchMediaAction = (input: {
  q?: string;
  kind?: "IMAGE" | "DOCUMENT" | "";
  page?: number;
  pageSize?: number;
}) => callAction<MediaSearchResult>("media/search", input);

export const getMediaAssetAction = (id: string) => callAction<MediaAssetDTO>("media/get", { id });

export const updateMediaAction = (id: string, input: MediaMetaValues) =>
  callAction<MediaAssetDTO>("media/update", { id, input });

export const getMediaUsageAction = (id: string) => callAction<MediaUsage[]>("media/usage", { id });

export const deleteMediaAction = (id: string) => callAction<{ id: string }>("media/delete", { id });
