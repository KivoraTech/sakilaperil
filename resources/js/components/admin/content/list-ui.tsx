import Link from "next/link";
import { ChevronLeft, ChevronRight, ExternalLink, Search } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import type { ContentStatusValue } from "@/lib/content/common";
import { cn } from "@/lib/utils";

/** Server-rendered list helpers shared by the content sections (GET forms, no client JS). */

export function StatusBadge({ status, scheduled }: { status: ContentStatusValue; scheduled?: boolean }) {
  if (status === "PUBLISHED" && scheduled)
    return (
      <Badge variant="outline" className="border-sky-300 bg-sky-50 text-sky-900">
        Scheduled
      </Badge>
    );
  const styles: Record<ContentStatusValue, string> = {
    PUBLISHED: "border-emerald-300 bg-emerald-50 text-emerald-900",
    DRAFT: "border-amber-300 bg-amber-50 text-amber-900",
    ARCHIVED: "border-zinc-300 bg-zinc-100 text-zinc-700",
  };
  const label: Record<ContentStatusValue, string> = {
    PUBLISHED: "Published",
    DRAFT: "Draft",
    ARCHIVED: "Archived",
  };
  return (
    <Badge variant="outline" className={styles[status]}>
      {label[status]}
    </Badge>
  );
}

export function ListToolbar({
  q,
  status,
  placeholder = "Search by title",
  extra,
  showStatus = true,
}: {
  q: string;
  status?: string;
  placeholder?: string;
  extra?: React.ReactNode;
  showStatus?: boolean;
}) {
  return (
    <form role="search" className="mb-4 flex flex-wrap items-end gap-2" method="get">
      <div className="relative min-w-52 flex-1">
        <Search
          className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden
        />
        <Input
          name="q"
          type="search"
          defaultValue={q}
          placeholder={placeholder}
          aria-label="Search"
          className="pl-8"
        />
      </div>
      {showStatus ? (
        <select
          name="status"
          defaultValue={status ?? ""}
          aria-label="Filter by status"
          className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
        >
          <option value="">All statuses</option>
          <option value="DRAFT">Draft</option>
          <option value="PUBLISHED">Published</option>
          <option value="ARCHIVED">Archived</option>
        </select>
      ) : null}
      {extra}
      <Button type="submit" variant="secondary">
        Filter
      </Button>
    </form>
  );
}

export function Pagination({
  page,
  pageCount,
  total,
  params,
}: {
  page: number;
  pageCount: number;
  total: number;
  params: Record<string, string | undefined>;
}) {
  if (pageCount <= 1) return <p className="mt-4 text-sm text-muted-foreground">{total} item(s)</p>;
  const href = (p: number) => {
    const sp = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v) sp.set(k, v);
    sp.set("page", String(p));
    return `?${sp.toString()}`;
  };
  return (
    <nav aria-label="Pagination" className="mt-4 flex items-center justify-between gap-2 text-sm">
      <span className="text-muted-foreground">
        Page {page} of {pageCount} · {total} item(s)
      </span>
      <span className="flex gap-2">
        {page > 1 ? (
          <Button asChild variant="outline" size="sm">
            <Link prefetch={false} href={href(page - 1)} rel="prev">
              <ChevronLeft aria-hidden /> Previous
            </Link>
          </Button>
        ) : null}
        {page < pageCount ? (
          <Button asChild variant="outline" size="sm">
            <Link prefetch={false} href={href(page + 1)} rel="next">
              Next <ChevronRight aria-hidden />
            </Link>
          </Button>
        ) : null}
      </span>
    </nav>
  );
}

export function EmptyState({
  title,
  description,
  action,
  className,
}: {
  title: string;
  description?: string;
  action?: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "grid place-items-center gap-2 rounded-xl border border-dashed bg-background p-10 text-center",
        className,
      )}
    >
      <p className="font-medium">{title}</p>
      {description ? <p className="max-w-md text-sm text-muted-foreground">{description}</p> : null}
      {action ? <div className="mt-2">{action}</div> : null}
    </div>
  );
}

export function LocaleBadges({ locales }: { locales: string[] }) {
  return (
    <span className="flex gap-1">
      {["it", "en"].map((l) => (
        <span
          key={l}
          title={
            locales.includes(l)
              ? `${l.toUpperCase()} version exists`
              : `No ${l.toUpperCase()} version (falls back to Italian)`
          }
          className={cn(
            "rounded px-1 text-[10px] font-semibold uppercase",
            locales.includes(l)
              ? "bg-primary/10 text-primary"
              : "bg-muted text-muted-foreground line-through",
          )}
        >
          {l}
        </span>
      ))}
    </span>
  );
}

export function formatDate(iso: string | Date | null | undefined, withTime = false): string {
  if (!iso) return "—";
  const d = typeof iso === "string" ? new Date(iso) : iso;
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    ...(withTime ? { hour: "2-digit", minute: "2-digit" } : {}),
    timeZone: "Europe/Rome",
  }).format(d);
}

/** Standard content table (title → edit link, status, languages, meta, dates). */
export function ContentTable({
  items,
  basePath,
  metaLabel = "Details",
  dateLabel = "Updated",
  dateOf = (i) => i.updatedAt,
  publicPath,
}: {
  items: {
    id: string;
    title: string;
    slug?: string | null;
    status: ContentStatusValue;
    scheduled: boolean;
    locales: string[];
    updatedAt: string;
    publishedAt: string | null;
    meta: string | null;
    canEdit: boolean;
  }[];
  basePath: string;
  metaLabel?: string;
  dateLabel?: string;
  dateOf?: (i: { updatedAt: string; publishedAt: string | null }) => string | null;
  /** Public URL for a slug (e.g. "/it/news/<slug>"): live rows get a "View on website" link. */
  publicPath?: (slug: string) => string;
}) {
  return (
    <div className="overflow-x-auto rounded-xl border bg-card">
      <table className="w-full text-sm">
        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
          <tr>
            <th scope="col" className="px-3 py-2 font-medium">
              Title
            </th>
            <th scope="col" className="px-3 py-2 font-medium">
              Status
            </th>
            <th scope="col" className="hidden px-3 py-2 font-medium sm:table-cell">
              Languages
            </th>
            <th scope="col" className="hidden px-3 py-2 font-medium md:table-cell">
              {metaLabel}
            </th>
            <th scope="col" className="hidden px-3 py-2 font-medium lg:table-cell">
              {dateLabel}
            </th>
          </tr>
        </thead>
        <tbody>
          {items.map((i) => (
            <tr key={i.id} className="border-b last:border-0 hover:bg-muted/30">
              <td className="max-w-[28rem] px-3 py-2.5">
                <Link
                  prefetch={false}
                  href={`${basePath}/${i.id}`}
                  className="inline-flex min-h-6 items-center font-medium underline-offset-2 hover:underline"
                >
                  {i.title}
                </Link>
                {!i.canEdit ? <span className="ml-2 text-xs text-muted-foreground">(view only)</span> : null}
                {publicPath && i.slug && i.status === "PUBLISHED" && !i.scheduled ? (
                  <a
                    href={publicPath(i.slug)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex min-h-6 w-fit items-center gap-1 text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                    aria-label={`View ${i.title} on the website (opens in a new tab)`}
                  >
                    View on website <ExternalLink className="size-3" aria-hidden />
                  </a>
                ) : null}
              </td>
              <td className="px-3 py-2.5">
                <StatusBadge status={i.status} scheduled={i.scheduled} />
              </td>
              <td className="hidden px-3 py-2.5 sm:table-cell">
                <LocaleBadges locales={i.locales} />
              </td>
              <td className="hidden px-3 py-2.5 text-muted-foreground md:table-cell">{i.meta ?? "—"}</td>
              <td className="hidden px-3 py-2.5 whitespace-nowrap text-muted-foreground lg:table-cell">
                {formatDate(dateOf(i))}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
