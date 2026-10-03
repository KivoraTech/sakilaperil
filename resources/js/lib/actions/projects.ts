import type { ContentStatusValue, SaveIntent } from "@/lib/content/common";
import type { ProjectFormValues } from "@/lib/content/schemas";
import { callAction } from "./client";

/**
 * Port of sakila/lib/actions/projects.ts: same exported names and signatures; the work happens in
 * App\Http\Controllers\Admin\ProjectsController (POST /admin/actions/projects/*).
 */
export const saveProjectAction = (id: string | null, input: ProjectFormValues, intent: SaveIntent = "save") =>
  callAction<{ id: string }>("projects/save", { id, input, intent });

export const setProjectStatusAction = (id: string, status: ContentStatusValue) =>
  callAction<{ id: string }>("projects/set-status", { id, status });

export const deleteProjectAction = (id: string) => callAction<{ id: string }>("projects/delete", { id });
