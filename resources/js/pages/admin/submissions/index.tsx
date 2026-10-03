import Link from "next/link";
import { Download, Inbox, Search } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { Head } from "@inertiajs/react";
import { DeleteSubmissionButton } from "@/components/admin/engagement/submission-controls";
import { statusLabel, statusTone } from "@/components/admin/engagement/labels";

type InboxType = "contact" | "volunteer" | "membership" | "partner" | "newsletter";
type InboxRow = {
  id: string;
  name: string;
  email: string;
  preview: string;
  status: string;
  unread: boolean;
  locale: string;
  createdAt: string;
};
type Props = {
  params: { type: InboxType; view: string; q: string | null; page: number };
  views: string[];
  data: { items: InboxRow[]; total: number; page: number; pageCount: number };
  counts: Record<InboxType, number>;
};

const TABS: { type: InboxType; label: string }[] = [
  { type: "contact", label: "Contact" },
  { type: "volunteer", label: "Volunteers" },
  { type: "membership", label: "Membership" },
  { type: "partner", label: "Partners" },
  { type: "newsletter", label: "Newsletter" },
];

const VIEW_LABELS: Record<string, string> = {
  inbox: "Inbox",
  unread: "Unread",
  archived: "Archived",
  all: "All",
  PENDING: "Pending",
  SUBSCRIBED: "Subscribed",
  UNSUBSCRIBED: "Unsubscribed",
};

const dateFmt = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "Europe/Rome",
});

/** Port of app/admin/(shell)/submissions/page.tsx (data from SubmissionsController). */
export default function SubmissionsPage({ params, views, data, counts }: Props) {
  const isNewsletter = params.type === "newsletter";
  const view = params.view;
  const { items, total, page, pageCount } = data;

  const hrefFor = (next: Partial<{ type: InboxType; view: string; q: string; page: number }>) => {
    const merged = { type: params.type, view, q: params.q, page: params.page, ...next };
    const p = new URLSearchParams({ type: merged.type });
    if (merged.view && merged.view !== views[0]) p.set("view", merged.view);
    if (merged.q) p.set("q", merged.q);
    if (merged.page && merged.page > 1) p.set("page", String(merged.page));
    return `/admin/submissions?${p}`;
  };
  const exportHref = `/admin/submissions/export?${new URLSearchParams({
    type: params.type,
    view,
    ...(params.q ? { q: params.q } : {}),
  })}`;

  return (
    <>
      <Head title="Submissions" />
      <AdminPageHeader
        title="Submissions"
        description="Contact messages, volunteer, membership and partner applications, and newsletter subscribers. Personal data: handle with care."
        actions={
          <Button asChild variant="outline" size="sm">
            <a href={exportHref} download>
              <Download aria-hidden="true" /> Export CSV
            </a>
          </Button>
        }
      />

      <nav aria-label="Submission type" className="mb-4 flex flex-wrap gap-1 border-b">
        {TABS.map((tab) => {
          const active = tab.type === params.type;
          const count = counts[tab.type];
          return (
            <Link
              prefetch={false}
              key={tab.type}
              href={`/admin/submissions?type=${tab.type}`}
              aria-current={active ? "page" : undefined}
              className={cn(
                "-mb-px flex items-center gap-2 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground",
                active && "border-primary text-foreground",
              )}
            >
              {tab.label}
              {count ? (
                <Badge
                  variant={tab.type === "newsletter" ? "secondary" : "default"}
                  aria-label={`${count} ${tab.type === "newsletter" ? "pending" : "unread"}`}
                >
                  {count}
                </Badge>
              ) : null}
            </Link>
          );
        })}
      </nav>

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <form role="search" action="/admin/submissions" className="relative w-full sm:w-72">
          <Search
            className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <input type="hidden" name="type" value={params.type} />
          {view !== views[0] ? <input type="hidden" name="view" value={view} /> : null}
          <Input
            name="q"
            defaultValue={params.q ?? ""}
            placeholder="Search name, email…"
            aria-label="Search submissions"
            className="pl-8"
          />
        </form>
        <nav aria-label="Filter" className="flex flex-wrap gap-1">
          {views.map((v) => (
            <Link
              prefetch={false}
              key={v}
              href={hrefFor({ view: v, page: 1 })}
              aria-current={view === v ? "page" : undefined}
              className={cn(
                "rounded-full border px-3 py-1 text-sm transition-colors hover:bg-muted",
                view === v && "border-primary bg-primary text-primary-foreground hover:bg-primary/90",
              )}
            >
              {VIEW_LABELS[v] ?? v}
            </Link>
          ))}
        </nav>
      </div>

      {items.length === 0 ? (
        <div className="rounded-xl border border-dashed bg-background p-12 text-center">
          <Inbox className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
          <p className="mt-3 font-medium">Nothing here</p>
          <p className="mt-1 text-sm text-muted-foreground">
            {params.q ? (
              <Link prefetch={false} href={hrefFor({ q: "", page: 1 })} className="underline">
                Clear search
              </Link>
            ) : (
              "New submissions from the website will appear here."
            )}
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-background">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{isNewsletter ? "Email" : "From"}</TableHead>
                <TableHead className="hidden md:table-cell">{isNewsletter ? "Source" : "Summary"}</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="hidden sm:table-cell">Received</TableHead>
                {isNewsletter ? (
                  <TableHead className="text-right">
                    <span className="sr-only">Actions</span>
                  </TableHead>
                ) : null}
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((row) => (
                <TableRow key={row.id} data-testid="submission-row" data-unread={row.unread || undefined}>
                  <TableCell className="max-w-64">
                    {isNewsletter ? (
                      <span className="font-medium">{row.email}</span>
                    ) : (
                      <Link
                        href={`/admin/submissions/${params.type}/${row.id}`}
                        prefetch={false}
                        className={cn(
                          "flex items-center gap-2 hover:underline",
                          row.unread ? "font-semibold" : "font-normal",
                        )}
                      >
                        {row.unread ? (
                          <span className="size-2 shrink-0 rounded-full bg-primary" aria-hidden="true" />
                        ) : null}
                        <span className="truncate">{row.name}</span>
                        {row.unread ? <span className="sr-only">(unread)</span> : null}
                      </Link>
                    )}
                    {!isNewsletter ? (
                      <span className="block truncate text-xs text-muted-foreground">{row.email}</span>
                    ) : null}
                  </TableCell>
                  <TableCell className="hidden max-w-96 text-sm text-muted-foreground md:table-cell">
                    <span className="line-clamp-2">{row.preview || "—"}</span>
                  </TableCell>
                  <TableCell>
                    <Badge variant={statusTone(row.status)}>{statusLabel(row.status)}</Badge>
                    <span className="ml-2 text-xs text-muted-foreground uppercase">{row.locale}</span>
                  </TableCell>
                  <TableCell className="hidden text-sm whitespace-nowrap text-muted-foreground sm:table-cell">
                    {dateFmt.format(new Date(row.createdAt))}
                  </TableCell>
                  {isNewsletter ? (
                    <TableCell className="text-right">
                      <DeleteSubmissionButton
                        type="newsletter"
                        id={row.id}
                        label={row.email}
                        size="icon-sm"
                      />
                    </TableCell>
                  ) : null}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <nav
        aria-label="Pagination"
        className="mt-4 flex items-center justify-between text-sm text-muted-foreground"
      >
        <span>
          {total} {total === 1 ? "item" : "items"} · page {page} of {pageCount}
        </span>
        <span className="flex gap-2">
          {page > 1 ? (
            <Button asChild variant="outline" size="sm">
              <Link prefetch={false} href={hrefFor({ page: page - 1 })}>
                Previous
              </Link>
            </Button>
          ) : null}
          {page < pageCount ? (
            <Button asChild variant="outline" size="sm">
              <Link prefetch={false} href={hrefFor({ page: page + 1 })}>
                Next
              </Link>
            </Button>
          ) : null}
        </span>
      </nav>
    </>
  );
}
