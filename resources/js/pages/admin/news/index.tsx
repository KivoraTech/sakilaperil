import Link from "next/link";
import { Head } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { ContentTable, EmptyState, ListToolbar, Pagination } from "@/components/admin/content/list-ui";
import { Button } from "@/components/ui/button";
import type { ListPageProps } from "@/lib/content/admin-types";

/** Port of app/admin/(shell)/news/page.tsx (data from App\Http\Controllers\Admin\NewsController::index). */
export default function NewsListPage({ params, list, mine }: ListPageProps & { mine: boolean }) {
  const filtered = !!(params.q || params.status || mine);
  return (
    <>
      <Head title="News & stories" />
      <AdminPageHeader
        title="News & stories"
        description="Updates and stories from the field, in Italian and English."
        actions={
          <Button asChild>
            <Link prefetch={false} href="/admin/news/new">
              <Plus aria-hidden /> Write a news story
            </Link>
          </Button>
        }
      />
      <ListToolbar
        q={params.q}
        status={params.status}
        extra={
          <select
            name="mine"
            defaultValue={mine ? "1" : ""}
            aria-label="Filter by author"
            className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
          >
            <option value="">Everyone&apos;s posts</option>
            <option value="1">Only my posts</option>
          </select>
        }
      />
      {list.items.length ? (
        <>
          <ContentTable
            items={list.items}
            basePath="/admin/news"
            metaLabel="Category · author"
            publicPath={(slug) => `/it/news/${slug}`}
          />
          <Pagination
            page={list.page}
            pageCount={list.pageCount}
            total={list.total}
            params={{ q: params.q, status: params.status, mine: mine ? "1" : undefined }}
          />
        </>
      ) : (
        <EmptyState
          title={filtered ? "No posts match these filters" : "No posts yet"}
          description={
            filtered ? "Try another search or clear the filters." : "Share the first update from Nepal."
          }
          action={
            filtered ? (
              <Button asChild variant="outline">
                <Link prefetch={false} href="/admin/news">
                  Clear filters
                </Link>
              </Button>
            ) : (
              <Button asChild>
                <Link prefetch={false} href="/admin/news/new">
                  Write a post
                </Link>
              </Button>
            )
          }
        />
      )}
    </>
  );
}
