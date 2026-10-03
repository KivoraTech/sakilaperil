/** Types of the navigation editor data (reference: lib/queries/admin/navigation.ts). */
import type { NavigationKeyName, NavItemInput } from "@/lib/navigation/schema";

export type NavEditorItem = NavItemInput & { key: string };

export type NavEditorData = {
  menus: { key: NavigationKeyName; items: NavEditorItem[]; updatedAt: string | null }[];
  pages: { id: string; title: string; isPublished: boolean }[];
};
