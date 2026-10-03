import Link from "next/link";
import { Head } from "@inertiajs/react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { EmptyState, Pagination } from "@/components/admin/content/list-ui";
import { MediaLibrary } from "@/components/admin/media/MediaLibrary";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import type { MediaSearchResult } from "@/lib/actions/media";

type Props = {
  q: string;
  kind: "" | "IMAGE" | "DOCUMENT";
  list: MediaSearchResult;
  canEdit: boolean;
  canDelete: boolean;
};

/** Port of app/admin/(shell)/media/page.tsx (data from MediaController::index). */
export default function MediaPage({ q, kind, list, canEdit, canDelete }: Props) {
  return (
    <>
      <Head title="Photos & files" />
      <AdminPageHeader
        title="Photos & files"
        description="Photos and PDFs used across the site. Videos are embedded from YouTube or Vimeo."
      />
      <form role="search" method="get" className="mb-6 flex flex-wrap items-center gap-2">
        <Input
          type="search"
          name="q"
          defaultValue={q}
          placeholder="Search file name, alt text or credit"
          aria-label="Search media"
          className="max-w-sm"
        />
        <select
          name="kind"
          defaultValue={kind}
          aria-label="File type"
          className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
        >
          <option value="">All files</option>
          <option value="IMAGE">Images</option>
          <option value="DOCUMENT">PDF documents</option>
        </select>
        <Button type="submit" variant="secondary">
          Filter
        </Button>
        {q || kind ? (
          <Button asChild variant="ghost">
            <Link prefetch={false} href="/admin/media">
              Clear
            </Link>
          </Button>
        ) : null}
      </form>
      <MediaLibrary items={list.items} canEdit={canEdit} canDelete={canDelete} />
      {!list.items.length ? (
        <EmptyState
          className="mt-6"
          title={q || kind ? "No files match" : "No files yet"}
          description={q || kind ? "Try another search." : "Drop photos or PDFs above to start the library."}
        />
      ) : (
        <Pagination page={list.page} pageCount={list.pageCount} total={list.total} params={{ q, kind }} />
      )}
    </>
  );
}
