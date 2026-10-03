import Link from "next/link";
import { Head } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { ContentTable, EmptyState, ListToolbar, Pagination } from "@/components/admin/content/list-ui";
import { Button } from "@/components/ui/button";
import type { ListPageProps } from "@/lib/content/admin-types";

/** Port of app/admin/(shell)/reports/page.tsx (data from ReportsController::index). */
export default function ReportsListPage({ params, list, canManage }: ListPageProps & { canManage: boolean }) {
  const filtered = !!(params.q || params.status);
  return (
    <>
      <Head title="Reports" />
      <AdminPageHeader
        title="Reports & documents"
        description="Annual reports, financial statements and other documents (PDF) listed on the Documents page."
        actions={
          canManage ? (
            <Button asChild>
              <Link prefetch={false} href="/admin/reports/new">
                <Plus aria-hidden /> New report
              </Link>
            </Button>
          ) : undefined
        }
      />
      {!canManage ? (
        <p className="mb-4 rounded-lg border bg-muted/40 px-3 py-2 text-sm">
          Reports are managed by editors.
        </p>
      ) : null}
      <ListToolbar q={params.q} status={params.status} placeholder="Search by title or year" />
      {list.items.length ? (
        <>
          <ContentTable items={list.items} basePath="/admin/reports" metaLabel="Year · type" />
          <Pagination
            page={list.page}
            pageCount={list.pageCount}
            total={list.total}
            params={{ q: params.q, status: params.status }}
          />
        </>
      ) : (
        <EmptyState
          title={filtered ? "No reports match these filters" : "No reports yet"}
          description={
            filtered ? undefined : "Upload the latest annual report so donors can see how funds are used."
          }
          action={
            canManage || filtered ? (
              <Button asChild variant={filtered ? "outline" : "default"}>
                <Link prefetch={false} href={filtered ? "/admin/reports" : "/admin/reports/new"}>
                  {filtered ? "Clear filters" : "Add a report"}
                </Link>
              </Button>
            ) : undefined
          }
        />
      )}
    </>
  );
}
