import type { ContentStatusValue, ListParams } from "./common";

/**
 * Props the Laravel content controllers pass to the Inertia pages (ports of the reference's
 * lib/queries/admin/{shared-content,news,projects,events,gallery,reports}.ts return types).
 */
export type AdminListItem = {
  id: string;
  title: string;
  slug: string | null;
  status: ContentStatusValue;
  scheduled: boolean;
  locales: string[];
  updatedAt: string;
  publishedAt: string | null;
  meta: string | null;
  canEdit: boolean;
};

export type AdminList = { items: AdminListItem[]; total: number; page: number; pageCount: number };

export type ListPageProps = { params: ListParams; list: AdminList };

export type EditorPermsProps = { canEdit: boolean; canPublish: boolean; canDelete: boolean };

export type SelectOption = { value: string; label: string };
