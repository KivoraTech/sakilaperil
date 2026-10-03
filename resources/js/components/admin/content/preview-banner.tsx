import Link from "next/link";
import { localeLabels, locales, type Locale } from "@/lib/i18n/config";

/**
 * Sticky "not live" banner for content previews (news, projects, events), matching the page
 * preview's banner so editors always know they are not looking at the public site.
 */
export function ContentPreviewBanner({
  title,
  kindLabel,
  locale,
  status,
  editHref,
  previewPathFor,
  isFallback,
}: {
  title: string;
  kindLabel: string;
  locale: Locale;
  status: "DRAFT" | "PUBLISHED" | "ARCHIVED";
  editHref: string;
  previewPathFor: (locale: Locale) => string;
  isFallback: boolean;
}) {
  const state =
    status === "PUBLISHED"
      ? "Live on the website"
      : status === "ARCHIVED"
        ? "Archived, not live"
        : "Draft, not live";
  return (
    <div
      role="region"
      aria-label="Preview notice"
      data-testid="preview-banner"
      className="sticky top-0 z-[100] border-b-4 border-marigold bg-deep text-white shadow-lg"
    >
      <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-sm">
        <strong className="rounded bg-marigold px-2 py-0.5 text-xs font-bold tracking-widest text-deep uppercase">
          ANTEPRIMA / PREVIEW — NOT LIVE
        </strong>
        <span className="min-w-0 flex-1 truncate">
          {kindLabel} · {title} · {state}
          {isFallback ? " · No English version yet: showing Italian" : ""}
        </span>
        <nav aria-label="Preview language" className="flex gap-1">
          {locales.map((l) => (
            <Link
              prefetch={false}
              key={l}
              href={previewPathFor(l)}
              aria-current={l === locale ? "page" : undefined}
              className="rounded px-2 py-0.5 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white aria-[current=page]:bg-white/20 aria-[current=page]:font-semibold"
            >
              {localeLabels[l]}
            </Link>
          ))}
        </nav>
        <Link
          prefetch={false}
          href={editHref}
          className="rounded px-2 py-0.5 font-semibold underline underline-offset-4 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white"
        >
          Back to editor
        </Link>
      </div>
    </div>
  );
}
