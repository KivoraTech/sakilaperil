import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";

/** Server-rendered pagination (plain links, works without JS). Shared by builder-owned lists. */
export function Pagination({
  page,
  pageCount,
  total,
  hrefFor,
  noun = "items",
}: {
  page: number;
  pageCount: number;
  total: number;
  hrefFor: (page: number) => string;
  noun?: string;
}) {
  if (total === 0) return null;
  const linkCls =
    "inline-flex h-8 items-center gap-1 rounded-lg border px-2.5 text-sm hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 outline-none";
  return (
    <nav aria-label="Pagination" className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
      <p className="text-muted-foreground">
        {total} {noun} · page {page} of {pageCount}
      </p>
      {pageCount > 1 ? (
        <div className="flex gap-2">
          {page > 1 ? (
            <Link prefetch={false} href={hrefFor(page - 1)} className={linkCls} rel="prev">
              <ChevronLeft className="size-4" aria-hidden /> Previous
            </Link>
          ) : (
            <span className={cn(linkCls, "pointer-events-none opacity-50")} aria-disabled="true">
              <ChevronLeft className="size-4" aria-hidden /> Previous
            </span>
          )}
          {page < pageCount ? (
            <Link prefetch={false} href={hrefFor(page + 1)} className={linkCls} rel="next">
              Next <ChevronRight className="size-4" aria-hidden />
            </Link>
          ) : (
            <span className={cn(linkCls, "pointer-events-none opacity-50")} aria-disabled="true">
              Next <ChevronRight className="size-4" aria-hidden />
            </span>
          )}
        </div>
      ) : null}
    </nav>
  );
}
