import Link from "next/link";
import { Head } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { CreateTranslationForm } from "@/components/admin/page-builder/create-translation-form";
import { PageBuilder } from "@/components/admin/page-builder/page-builder";
import { LocaleStatusChips } from "@/components/admin/page-builder/status";
import { localeLabels, locales, type Locale } from "@/lib/i18n/config";
import type { BuilderReferences, LinkSuggestion, PageEditorData } from "@/lib/queries/admin/pages";
import { cn } from "@/lib/utils";

type Props = {
  id: string;
  locale: Locale;
  data: PageEditorData;
  references: BuilderReferences | null;
  linkSuggestions: LinkSuggestion[];
  permissions: { canPublish: boolean; canDelete: boolean; canCreate: boolean };
};

/** Port of app/admin/(shell)/pages/[id]/page.tsx (data from App\Http\Controllers\Admin\PagesController). */
export default function EditPage({ id, locale, data, references, linkSuggestions, permissions }: Props) {
  const title =
    data.translations.find((t) => t.locale === "it")?.title ?? data.translations[0]?.title ?? "Page";
  const current = data.translations.find((t) => t.locale === locale);
  const publicPath = current?.isPublished
    ? current.slug === "home"
      ? `/${locale}`
      : `/${locale}/${current.slug}`
    : null;
  const otherLocales = locales.filter((l) => l !== locale && data.translations.some((t) => t.locale === l));

  return (
    <>
      <Head title="Edit page" />
      <Link
        prefetch={false}
        href="/admin/pages"
        className="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft className="size-4" aria-hidden /> All pages
      </Link>
      <AdminPageHeader
        title={title}
        description={
          data.page.key
            ? `System page (${data.page.key}) — linked from the site's code, cannot be deleted.`
            : undefined
        }
      />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="Language version" className="inline-flex rounded-lg border bg-background p-1">
          {locales.map((l) => {
            const exists = data.translations.some((t) => t.locale === l);
            return (
              <Link
                prefetch={false}
                key={l}
                href={`/admin/pages/${id}?locale=${l}`}
                aria-current={l === locale ? "page" : undefined}
                className={cn(
                  "rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground",
                  l === locale && "bg-primary text-primary-foreground hover:text-primary-foreground",
                )}
              >
                {localeLabels[l]}
                {!exists ? <span className="ml-1 text-xs opacity-80">(missing)</span> : null}
              </Link>
            );
          })}
        </nav>
        <LocaleStatusChips locales={data.translations} />
      </div>

      {data.draft && references ? (
        <PageBuilder
          // Keyed by language only: the builder resets itself when the saved draft changes elsewhere
          // (restore, discard, publish), and stays mounted across its own saves (keeps the preview).
          key={locale}
          pageId={id}
          pageKey={data.page.key}
          locale={locale}
          draft={data.draft}
          references={{ ...references, linkSuggestions }}
          permissions={permissions}
          publicPath={publicPath}
          previewPath={`/preview/${locale}/pages/${id}`}
          otherLocales={otherLocales}
          menuReferences={data.page.menuReferences}
        />
      ) : permissions.canCreate ? (
        <CreateTranslationForm
          pageId={id}
          locale={locale}
          isHome={data.page.key === "home"}
          copyFrom={otherLocales[0] ?? null}
        />
      ) : (
        <div className="rounded-xl border border-dashed bg-background p-10 text-center text-sm text-muted-foreground">
          The {localeLabels[locale]} version of this page does not exist yet. Ask an editor to create it.
        </div>
      )}
    </>
  );
}
