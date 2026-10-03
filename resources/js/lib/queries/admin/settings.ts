/** Types of the settings editor data (reference: lib/queries/admin/settings.ts; built by SettingsController). */
import type { SiteSettingsData } from "@/lib/settings/schema";

export type SettingsEditorData = {
  settings: SiteSettingsData;
  invalid: string[];
  updatedAt: string | null;
  updatedBy: string | null;
};
