import type { ContentStatusValue, SaveIntent } from "@/lib/content/common";
import type { ReportFormValues } from "@/lib/content/schemas";
import { callAction } from "./client";

/**
 * Port of sakila/lib/actions/reports.ts: same exported names and signatures; the work happens in
 * App\Http\Controllers\Admin\ReportsController (POST /admin/actions/reports/*).
 */
export const saveReportAction = (id: string | null, input: ReportFormValues, intent: SaveIntent = "save") =>
  callAction<{ id: string }>("reports/save", { id, input, intent });

export const setReportStatusAction = (id: string, status: ContentStatusValue) =>
  callAction<{ id: string }>("reports/set-status", { id, status });

export const deleteReportAction = (id: string) => callAction<{ id: string }>("reports/delete", { id });
