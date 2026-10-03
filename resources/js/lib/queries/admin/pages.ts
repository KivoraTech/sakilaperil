/**
 * Types of the page-builder admin reads (reference: lib/queries/admin/pages.ts). The data is
 * produced by App\Queries\Admin\PagesQuery and arrives as Inertia props.
 */
import type { Locale } from "@/lib/i18n/config";
import type { SectionIssue } from "@/lib/pages/publish";

export const PAGES_PER_PAGE = 20;

export type LocaleState = {
  locale: Locale;
  title: string;
  slug: string;
  isPublished: boolean;
  hasDraftChanges: boolean;
  publishedAt: string | null;
};

export type PageListRow = {
  id: string;
  key: string | null;
  status: "DRAFT" | "PUBLISHED" | "ARCHIVED";
  title: string;
  locales: LocaleState[];
  updatedAt: string;
  updatedBy: string | null;
};

export type EditorSection = {
  id: string;
  type: string;
  isVisible: boolean;
  content: Record<string, unknown>;
  settings: Record<string, unknown>;
  /** Stored with an older block version and migrated for editing (saved as current version). */
  migrated: boolean;
  /** Block type or migration is unknown: shown read-only with a warning. */
  broken: boolean;
};

export type EditorRevision = { id: string; createdAt: string; createdBy: string | null; isLive: boolean };

export type PageEditorData = {
  page: { id: string; key: string | null; status: string; menuReferences: number };
  locale: Locale;
  translations: LocaleState[];
  /** Null when this language version does not exist yet. */
  draft: null | {
    title: string;
    slug: string;
    seoTitle: string;
    seoDescription: string;
    ogImageId: string;
    noIndex: boolean;
    isPublished: boolean;
    hasDraftChanges: boolean;
    publishedAt: string | null;
    version: string;
    updatedAt: string;
    sections: EditorSection[];
    issues: SectionIssue[];
    revisions: EditorRevision[];
  };
};

export type PickerAsset = {
  id: string;
  url: string;
  filename: string;
  alt: string;
  width: number | null;
  height: number | null;
};

export type PickerOption = { id: string; label: string; status?: string };

export type BuilderReferences = {
  images: PickerAsset[];
  projects: PickerOption[];
  albums: PickerOption[];
  newsCategories: PickerOption[];
};

export type LinkSuggestion = { href: string; label: string };
