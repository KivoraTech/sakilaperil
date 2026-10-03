import Link from "next/link";
import { Head } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { ContentTable, EmptyState, ListToolbar, Pagination } from "@/components/admin/content/list-ui";
import { Button } from "@/components/ui/button";
import type { ListPageProps } from "@/lib/content/admin-types";

/** Port of app/admin/(shell)/gallery/page.tsx (data from GalleryController::index). */
export default function GalleryListPage({ params, list, canManage }: ListPageProps & { canManage: boolean }) {
  const filtered = !!(params.q || params.status);
  return (
    <>
      <Head title="Gallery" />
      <AdminPageHeader
        title="Gallery"
        description="Photo and video albums. Photos come from the media library; videos from YouTube or Vimeo."
        actions={
          canManage ? (
            <Button asChild>
              <Link prefetch={false} href="/admin/gallery/new">
                <Plus aria-hidden /> New album
              </Link>
            </Button>
          ) : undefined
        }
      />
      {!canManage ? (
        <p className="mb-4 rounded-lg border bg-muted/40 px-3 py-2 text-sm">
          Albums are managed by editors. You can still upload photos in the Media library.
        </p>
      ) : null}
      <ListToolbar q={params.q} status={params.status} />
      {list.items.length ? (
        <>
          <ContentTable
            items={list.items}
            basePath="/admin/gallery"
            metaLabel="Items"
            publicPath={(slug) => `/it/gallery/${slug}`}
          />
          <Pagination
            page={list.page}
            pageCount={list.pageCount}
            total={list.total}
            params={{ q: params.q, status: params.status }}
          />
        </>
      ) : (
        <EmptyState
          title={filtered ? "No albums match these filters" : "No albums yet"}
          action={
            canManage || filtered ? (
              <Button asChild variant={filtered ? "outline" : "default"}>
                <Link prefetch={false} href={filtered ? "/admin/gallery" : "/admin/gallery/new"}>
                  {filtered ? "Clear filters" : "Create an album"}
                </Link>
              </Button>
            ) : undefined
          }
        />
      )}
    </>
  );
}
