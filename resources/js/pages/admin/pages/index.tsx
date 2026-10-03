import Link from "next/link";
import { ExternalLink, FileText, Search } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { CreatePageDialog } from "@/components/admin/page-builder/create-page-dialog";
import { Pagination } from "@/components/admin/page-builder/pagination";
import { LocaleStatusChips } from "@/components/admin/page-builder/status";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Head } from "@inertiajs/react";
import type { PageListRow } from "@/lib/queries/admin/pages";
import { cn } from "@/lib/utils";

type Params = { q: string | null; status: "all" | "published" | "draft" | "changes"; page: number };

type Props = {
  params: Params;
  items: PageListRow[];
  total: number;
  page: number;
  pageCount: number;
  openCreate: boolean;
  canCreate: boolean;
};

const FILTERS = [
  { value: "all", label: "All" },
  { value: "published", label: "Live" },
  { value: "draft", label: "Drafts" },
  { value: "changes", label: "Unpublished changes" },
] as const;

function relative(iso: string) {
  return new Intl.DateTimeFormat("en-GB", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Europe/Rome",
  }).format(new Date(iso));
}

/** Port of app/admin/(shell)/pages/page.tsx (data from App\Http\Controllers\Admin\PagesController). */
export default function PagesListPage({
  params,
  items,
  total,
  page,
  pageCount,
  openCreate,
  canCreate,
}: Props) {
  const hrefFor = (next: Partial<typeof params>) => {
    const p = new URLSearchParams();
    const merged = { ...params, ...next };
    if (merged.q) p.set("q", merged.q);
    if (merged.status !== "all") p.set("status", merged.status);
    if (merged.page > 1) p.set("page", String(merged.page));
    const s = p.toString();
    return `/admin/pages${s ? `?${s}` : ""}`;
  };

  return (
    <>
      <Head title="Pages" />
      <AdminPageHeader
        title="Pages"
        description="Build pages from sections, preview them and publish each language."
        actions={canCreate ? <CreatePageDialog defaultOpen={openCreate} /> : null}
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <form role="search" action="/admin/pages" className="relative w-full sm:w-72">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            name="q"
            defaultValue={params.q ?? ""}
            placeholder="Search title or address"
            aria-label="Search pages"
            className="pl-8"
          />
          {params.status !== "all" ? <input type="hidden" name="status" value={params.status} /> : null}
        </form>
        <nav aria-label="Filter by status" className="flex flex-wrap gap-1">
          {FILTERS.map((f) => (
            <Link
              prefetch={false}
              key={f.value}
              href={hrefFor({ status: f.value, page: 1 })}
              aria-current={params.status === f.value ? "page" : undefined}
              className={cn(
                "rounded-full border px-3 py-1 text-sm transition-colors hover:bg-muted",
                params.status === f.value &&
                  "border-primary bg-primary text-primary-foreground hover:bg-primary/90",
              )}
            >
              {f.label}
            </Link>
          ))}
        </nav>
      </div>

      {items.length === 0 ? (
        <div className="rounded-xl border border-dashed bg-background p-12 text-center">
          <FileText className="mx-auto size-8 text-muted-foreground" aria-hidden />
          <p className="mt-3 font-medium">
            {params.q || params.status !== "all" ? "No pages match" : "No pages yet"}
          </p>
          <p className="mt-1 text-sm text-muted-foreground">
            {params.q || params.status !== "all" ? (
              <Link prefetch={false} href="/admin/pages" className="underline">
                Clear filters
              </Link>
            ) : (
              "Create your first page to get started."
            )}
          </p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl border bg-background">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Page</TableHead>
                <TableHead>Languages</TableHead>
                <TableHead className="hidden md:table-cell">Last edited</TableHead>
                <TableHead className="text-right">
                  <span className="sr-only">Actions</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((p) => (
                <TableRow key={p.id}>
                  <TableCell className="max-w-72">
                    <Link
                      prefetch={false}
                      href={`/admin/pages/${p.id}`}
                      className="inline-flex min-h-6 items-center font-medium hover:underline"
                    >
                      {p.title}
                    </Link>
                    <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                      {p.locales.map((l) => {
                        const path = l.slug === "home" ? `/${l.locale}` : `/${l.locale}/${l.slug}`;
                        return l.isPublished ? (
                          <a
                            key={l.locale}
                            href={path}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex min-h-6 items-center gap-1 truncate underline-offset-4 hover:text-foreground hover:underline"
                            aria-label={`View ${p.title} (${l.locale.toUpperCase()}) on the website, opens in a new tab`}
                          >
                            {path} <ExternalLink className="size-3" aria-hidden />
                          </a>
                        ) : (
                          <span key={l.locale} className="truncate" title="Not live yet">
                            {path}
                          </span>
                        );
                      })}
                      {p.key ? <Badge variant="outline">System</Badge> : null}
                    </div>
                  </TableCell>
                  <TableCell>
                    <LocaleStatusChips locales={p.locales} />
                  </TableCell>
                  <TableCell className="hidden text-sm text-muted-foreground md:table-cell">
                    {relative(p.updatedAt)}
                    {p.updatedBy ? <span className="block text-xs">by {p.updatedBy}</span> : null}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button asChild size="sm" variant="outline">
                      <Link prefetch={false} href={`/admin/pages/${p.id}`} aria-label={`Edit ${p.title}`}>
                        Edit
                      </Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
      <Pagination
        page={page}
        pageCount={pageCount}
        total={total}
        hrefFor={(n) => hrefFor({ page: n })}
        noun="pages"
      />
    </>
  );
}
