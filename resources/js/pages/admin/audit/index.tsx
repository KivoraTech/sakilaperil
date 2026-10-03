import Link from "next/link";
import { ScrollText } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { Pagination } from "@/components/admin/page-builder/pagination";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { AuditAction } from "@/lib/audit/actions";
import { Head } from "@inertiajs/react";
import type { AuditData, AuditParams } from "@/lib/queries/admin/audit";

const selectCls =
  "h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 sm:w-48";

function tone(action: string): "default" | "secondary" | "destructive" | "outline" {
  if (/DELETED|DEACTIVATED|FAILED/.test(action)) return "destructive";
  if (/PUBLISHED|CREATED|LOGIN$/.test(action)) return "default";
  if (/UNPUBLISHED|REVERTED|ROLE_CHANGED|PASSWORD/.test(action)) return "outline";
  return "secondary";
}

const pretty = (a: string) => a.toLowerCase().replace(/_/g, " ");

/** Port of app/admin/(shell)/audit/page.tsx (data from AuditController). */
export default function AuditPage({ params, data }: { params: AuditParams; data: AuditData }) {
  const hrefFor = (next: Partial<AuditParams>) => {
    const merged = { ...params, ...next };
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(merged)) if (v && !(k === "page" && v === 1)) p.set(k, String(v));
    const s = p.toString();
    return `/admin/audit${s ? `?${s}` : ""}`;
  };
  const filtered = !!(params.user || params.action || params.entity || params.q);

  return (
    <>
      <Head title="Audit log" />
      <AdminPageHeader
        title="Audit log"
        description="Who changed what, and when. Read-only; secrets are never recorded."
      />

      <form
        action="/admin/audit"
        className="mb-4 flex flex-wrap items-end gap-3 rounded-xl border bg-background p-3"
      >
        <div className="grid gap-1">
          <Label htmlFor="f-user" className="text-xs">
            User
          </Label>
          <select id="f-user" name="user" defaultValue={params.user ?? ""} className={selectCls}>
            <option value="">Everyone</option>
            {data.users.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-action" className="text-xs">
            Action
          </Label>
          <select id="f-action" name="action" defaultValue={params.action ?? ""} className={selectCls}>
            <option value="">All actions</option>
            {Object.values(AuditAction).map((a) => (
              <option key={a} value={a}>
                {pretty(a)}
              </option>
            ))}
          </select>
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-entity" className="text-xs">
            Item type
          </Label>
          <select id="f-entity" name="entity" defaultValue={params.entity ?? ""} className={selectCls}>
            <option value="">All types</option>
            {data.entityTypes.map((e) => (
              <option key={e} value={e}>
                {e}
              </option>
            ))}
          </select>
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-q" className="text-xs">
            Item ID
          </Label>
          <input
            id="f-q"
            name="q"
            defaultValue={params.q ?? ""}
            className={selectCls}
            placeholder="exact id"
          />
        </div>
        <Button type="submit" size="sm">
          Filter
        </Button>
        {filtered ? (
          <Link prefetch={false} href="/admin/audit" className="text-sm underline">
            Clear
          </Link>
        ) : null}
      </form>

      {data.rows.length === 0 ? (
        <div className="rounded-xl border border-dashed bg-background p-12 text-center">
          <ScrollText className="mx-auto size-8 text-muted-foreground" aria-hidden />
          <p className="mt-3 font-medium">
            {filtered ? "No entries match these filters" : "No activity recorded yet"}
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-background">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-44">When</TableHead>
                <TableHead>Who</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>Item</TableHead>
                <TableHead className="hidden lg:table-cell">Details</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.rows.map((r) => (
                <TableRow key={r.id} className="align-top">
                  <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                    <time dateTime={r.createdAt}>
                      {new Intl.DateTimeFormat("en-GB", {
                        dateStyle: "short",
                        timeStyle: "medium",
                        timeZone: "Europe/Rome",
                      }).format(new Date(r.createdAt))}
                    </time>
                  </TableCell>
                  <TableCell className="text-sm">
                    {r.user ? (
                      <Link
                        prefetch={false}
                        href={hrefFor({ user: r.userId ?? undefined, page: 1 })}
                        className="inline-flex min-h-6 items-center hover:underline"
                      >
                        {r.user}
                      </Link>
                    ) : (
                      <span className="text-muted-foreground">System / anonymous</span>
                    )}
                    {r.ipAddress ? (
                      <span className="block text-xs text-muted-foreground">{r.ipAddress}</span>
                    ) : null}
                  </TableCell>
                  <TableCell>
                    <Link
                      prefetch={false}
                      href={hrefFor({ action: r.action, page: 1 })}
                      className="inline-flex min-h-6 items-center"
                    >
                      <Badge variant={tone(r.action)}>{pretty(r.action)}</Badge>
                    </Link>
                  </TableCell>
                  <TableCell className="text-sm">
                    {r.entityType ? (
                      <>
                        <Link
                          prefetch={false}
                          href={hrefFor({ entity: r.entityType, page: 1 })}
                          className="inline-flex min-h-6 items-center hover:underline"
                        >
                          {r.entityType}
                        </Link>
                        {r.entityId ? (
                          <Link
                            prefetch={false}
                            href={hrefFor({ q: r.entityId, page: 1 })}
                            className="block min-h-6 max-w-40 truncate font-mono text-xs leading-6 text-muted-foreground hover:underline"
                          >
                            {r.entityId}
                          </Link>
                        ) : null}
                      </>
                    ) : (
                      "—"
                    )}
                  </TableCell>
                  <TableCell className="hidden max-w-md lg:table-cell">
                    {r.metadata ? (
                      <details>
                        <summary className="cursor-pointer text-xs text-muted-foreground">
                          Show details
                        </summary>
                        <pre className="mt-2 max-h-60 overflow-auto rounded-md bg-muted p-2 text-xs whitespace-pre-wrap">
                          {JSON.stringify(r.metadata, null, 2)}
                        </pre>
                      </details>
                    ) : null}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
      <Pagination
        page={data.page}
        pageCount={data.pageCount}
        total={data.total}
        hrefFor={(n) => hrefFor({ page: n })}
        noun="entries"
      />
    </>
  );
}
