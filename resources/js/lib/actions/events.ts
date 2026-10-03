import type { ContentStatusValue, SaveIntent } from "@/lib/content/common";
import type { EventFormValues } from "@/lib/content/schemas";
import { callAction } from "./client";

/**
 * Port of sakila/lib/actions/events.ts: same exported names and signatures; the work happens in
 * App\Http\Controllers\Admin\EventsController (POST /admin/actions/events/*).
 */
export const saveEventAction = (id: string | null, input: EventFormValues, intent: SaveIntent = "save") =>
  callAction<{ id: string }>("events/save", { id, input, intent });

export const setEventStatusAction = (id: string, status: ContentStatusValue) =>
  callAction<{ id: string }>("events/set-status", { id, status });

export const deleteEventAction = (id: string) => callAction<{ id: string }>("events/delete", { id });
