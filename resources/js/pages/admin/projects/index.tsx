import Link from "next/link";
import { Head } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { ContentTable, EmptyState, ListToolbar, Pagination } from "@/components/admin/content/list-ui";
import { Button } from "@/components/ui/button";
import type { ListPageProps } from "@/lib/content/admin-types";

/** Port of app/admin/(shell)/projects/page.tsx (data from ProjectsController::index). */
export default function ProjectsListPage({ params, list }: ListPageProps) {
  const filtered = !!(params.q || params.status);
  return (
    <>
      <Head title="Projects" />
      <AdminPageHeader
        title="Projects"
        description="Each project has its own public page, with goal, progress and photos."
        actions={
          <Button asChild>
            <Link prefetch={false} href="/admin/projects/new">
              <Plus aria-hidden /> New project
            </Link>
          </Button>
        }
      />
      <ListToolbar q={params.q} status={params.status} />
      {list.items.length ? (
        <>
          <ContentTable
            items={list.items}
            basePath="/admin/projects"
            metaLabel="Stage · area"
            publicPath={(slug) => `/it/projects/${slug}`}
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
          title={filtered ? "No projects match these filters" : "No projects yet"}
          action={
            <Button asChild variant={filtered ? "outline" : "default"}>
              <Link prefetch={false} href={filtered ? "/admin/projects" : "/admin/projects/new"}>
                {filtered ? "Clear filters" : "Add a project"}
              </Link>
            </Button>
          }
        />
      )}
    </>
  );
}
