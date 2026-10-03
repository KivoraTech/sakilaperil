import Link from "next/link";
import { Head } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { ContentTable, EmptyState, ListToolbar, Pagination } from "@/components/admin/content/list-ui";
import { Button } from "@/components/ui/button";
import type { ListPageProps } from "@/lib/content/admin-types";

/** Port of app/admin/(shell)/events/page.tsx (data from EventsController::index). */
export default function EventsListPage({
  params,
  list,
  when,
}: ListPageProps & { when: "" | "upcoming" | "past" }) {
  const filtered = !!(params.q || params.status || when);
  return (
    <>
      <Head title="Events" />
      <AdminPageHeader
        title="Events"
        description="Dinners, fundraisers and meetings for the public calendar."
        actions={
          <Button asChild>
            <Link prefetch={false} href="/admin/events/new">
              <Plus aria-hidden /> New event
            </Link>
          </Button>
        }
      />
      <ListToolbar
        q={params.q}
        status={params.status}
        extra={
          <select
            name="when"
            defaultValue={when}
            aria-label="Filter by date"
            className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
          >
            <option value="">All dates</option>
            <option value="upcoming">Upcoming</option>
            <option value="past">Past</option>
          </select>
        }
      />
      {list.items.length ? (
        <>
          <ContentTable
            items={list.items}
            basePath="/admin/events"
            metaLabel="Venue"
            dateLabel="Starts"
            dateOf={(i) => i.publishedAt}
            publicPath={(slug) => `/it/events/${slug}`}
          />
          <Pagination
            page={list.page}
            pageCount={list.pageCount}
            total={list.total}
            params={{ q: params.q, status: params.status, when }}
          />
        </>
      ) : (
        <EmptyState
          title={filtered ? "No events match these filters" : "No events yet"}
          action={
            <Button asChild variant={filtered ? "outline" : "default"}>
              <Link prefetch={false} href={filtered ? "/admin/events" : "/admin/events/new"}>
                {filtered ? "Clear filters" : "Add an event"}
              </Link>
            </Button>
          }
        />
      )}
    </>
  );
}
