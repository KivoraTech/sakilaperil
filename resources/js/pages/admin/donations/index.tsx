import Link from "next/link";
import { Download, HandHeart } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatMoney } from "@/lib/payments/schemas";
import { Head } from "@inertiajs/react";
import { statusLabel, statusTone } from "@/components/admin/engagement/labels";
import { RecordBankTransferDialog } from "@/components/admin/engagement/donation-controls";
import { PROVIDER_LABEL } from "@/components/admin/engagement/provider-labels";

const donationProviders = ["STRIPE", "PAYPAL", "ESEWA", "KHALTI", "BANK_TRANSFER"] as const;
const donationStatuses = ["PENDING", "SUCCEEDED", "FAILED", "CANCELED", "REFUNDED"] as const;

type ListDonationsParams = {
  provider?: string;
  status?: string;
  mode: "live" | "test" | "all";
  from?: string;
  to?: string;
  q?: string;
  page: number;
};
type DonationRow = {
  id: string;
  reference: string;
  provider: string;
  status: string;
  amount: number;
  currency: string;
  donorName: string | null;
  donorEmail: string | null;
  isAnonymous: boolean;
  isTestMode: boolean;
  createdAt: string;
  monthly: boolean;
  projectTitle: string | null;
};
type ProviderState = { configured: boolean; testMode: boolean };
type Props = {
  params: ListDonationsParams;
  data: {
    items: DonationRow[];
    total: number;
    page: number;
    pageCount: number;
    totals: { currency: string; amount: number; count: number }[];
  };
  projects: { id: string; title: string }[];
  canManage: boolean;
  providers: Record<"STRIPE" | "PAYPAL" | "ESEWA" | "KHALTI", ProviderState>;
};

const dateFmt = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "Europe/Rome",
});
const selectClass =
  "h-8 rounded-lg border border-input bg-background px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50";

function query(p: ListDonationsParams, extra: Record<string, string | number | undefined> = {}) {
  const q = new URLSearchParams();
  const merged: Record<string, string | number | undefined> = { ...p, ...extra };
  for (const [k, v] of Object.entries(merged)) {
    if (v === undefined || v === "" || (k === "mode" && v === "all") || (k === "page" && v === 1)) continue;
    q.set(k, String(v));
  }
  return q.toString();
}

/** Port of app/admin/(shell)/donations/page.tsx (data from DonationsController). */
export default function DonationsPage({ params, data, projects, canManage, providers: config }: Props) {
  const { items, total, page, pageCount, totals } = data;
  const filtered = !!(
    params.provider ||
    params.status ||
    params.from ||
    params.to ||
    params.q ||
    params.mode !== "all"
  );

  return (
    <>
      <Head title="Donations" />
      <AdminPageHeader
        title="Donations"
        description="Online donations are confirmed only by the payment provider (webhook or server-side check). Totals count succeeded gifts."
        actions={
          <>
            <Button asChild variant="outline" size="sm">
              <a href={`/admin/donations/export?${query(params, { page: undefined })}`} download>
                <Download aria-hidden="true" /> Export CSV
              </a>
            </Button>
            {canManage ? <RecordBankTransferDialog projects={projects} /> : null}
          </>
        }
      />

      <section aria-label="Payment providers" className="mb-5 flex flex-wrap gap-2 text-xs">
        {(["STRIPE", "PAYPAL", "ESEWA", "KHALTI"] as const).map((p) => (
          <span
            key={p}
            className="inline-flex items-center gap-1.5 rounded-full border bg-background px-3 py-1"
          >
            <span
              aria-hidden="true"
              className={`size-2 rounded-full ${config[p].configured ? (config[p].testMode ? "bg-amber-500" : "bg-emerald-600") : "bg-muted-foreground/40"}`}
            />
            {PROVIDER_LABEL[p]}:{" "}
            <strong>
              {config[p].configured ? (config[p].testMode ? "test mode" : "live") : "not configured"}
            </strong>
          </span>
        ))}
        <span className="inline-flex items-center gap-1.5 rounded-full border bg-background px-3 py-1">
          <span aria-hidden="true" className="size-2 rounded-full bg-emerald-600" />
          Bank transfer: <strong>always on</strong>
        </span>
      </section>

      <section aria-label="Totals" className="mb-5 grid gap-3 sm:grid-cols-3">
        {totals.length === 0 ? (
          <div className="rounded-xl border bg-background p-4 text-sm text-muted-foreground">
            No succeeded donations{filtered ? " for these filters" : " yet"}.
          </div>
        ) : (
          totals.map((t) => (
            <div
              key={t.currency}
              className="rounded-xl border bg-background p-4"
              data-testid={`total-${t.currency}`}
            >
              <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Total {t.currency}
              </p>
              <p className="mt-1 text-2xl font-semibold">{formatMoney(t.amount, t.currency, "it")}</p>
              <p className="text-xs text-muted-foreground">
                {t.count} succeeded {t.count === 1 ? "gift" : "gifts"}
                {params.mode === "all" ? " (incl. test)" : ""}
              </p>
            </div>
          ))
        )}
      </section>

      <form
        action="/admin/donations"
        className="mb-4 flex flex-wrap items-end gap-3 rounded-xl border bg-background p-3"
        aria-label="Filter donations"
      >
        <div className="grid gap-1">
          <Label htmlFor="f-q">Search</Label>
          <Input
            id="f-q"
            name="q"
            defaultValue={params.q ?? ""}
            placeholder="Reference, name, email"
            className="w-56"
          />
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-provider">Method</Label>
          <select
            id="f-provider"
            name="provider"
            defaultValue={params.provider ?? ""}
            className={selectClass}
          >
            <option value="">All</option>
            {donationProviders.map((p) => (
              <option key={p} value={p}>
                {PROVIDER_LABEL[p]}
              </option>
            ))}
          </select>
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-status">Status</Label>
          <select id="f-status" name="status" defaultValue={params.status ?? ""} className={selectClass}>
            <option value="">All</option>
            {donationStatuses.map((s) => (
              <option key={s} value={s}>
                {statusLabel(s)}
              </option>
            ))}
          </select>
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-mode">Mode</Label>
          <select id="f-mode" name="mode" defaultValue={params.mode} className={selectClass}>
            <option value="all">Live + test</option>
            <option value="live">Live only</option>
            <option value="test">Test only</option>
          </select>
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-from">From</Label>
          <Input id="f-from" name="from" type="date" defaultValue={params.from ?? ""} className="w-40" />
        </div>
        <div className="grid gap-1">
          <Label htmlFor="f-to">To</Label>
          <Input id="f-to" name="to" type="date" defaultValue={params.to ?? ""} className="w-40" />
        </div>
        <Button type="submit" size="sm">
          Apply
        </Button>
        {filtered ? (
          <Button asChild variant="ghost" size="sm">
            <Link prefetch={false} href="/admin/donations">
              Reset
            </Link>
          </Button>
        ) : null}
      </form>

      {items.length === 0 ? (
        <div className="rounded-xl border border-dashed bg-background p-12 text-center">
          <HandHeart className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
          <p className="mt-3 font-medium">No donations{filtered ? " match these filters" : " yet"}</p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-background">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Reference</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>Method</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="hidden md:table-cell">Donor</TableHead>
                <TableHead className="hidden lg:table-cell">Project</TableHead>
                <TableHead className="hidden sm:table-cell">Date</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((d) => {
                const monthly = d.monthly;
                return (
                  <TableRow key={d.id} data-testid="donation-row">
                    <TableCell>
                      <Link
                        href={`/admin/donations/${d.id}`}
                        prefetch={false}
                        className="font-mono text-xs font-medium hover:underline"
                      >
                        {d.reference}
                      </Link>
                      {d.isTestMode ? (
                        <Badge variant="outline" className="ml-2 border-amber-500 text-amber-700">
                          TEST
                        </Badge>
                      ) : null}
                    </TableCell>
                    <TableCell className="font-medium whitespace-nowrap">
                      {formatMoney(d.amount, d.currency, "it")}
                      {monthly ? <span className="ml-1 text-xs text-muted-foreground">/month</span> : null}
                    </TableCell>
                    <TableCell className="text-sm">{PROVIDER_LABEL[d.provider]}</TableCell>
                    <TableCell>
                      <Badge variant={statusTone(d.status)}>{statusLabel(d.status)}</Badge>
                    </TableCell>
                    <TableCell className="hidden max-w-56 text-sm md:table-cell">
                      <span className="block truncate">
                        {d.donorName || (d.isAnonymous ? "Anonymous" : "—")}
                      </span>
                      <span className="block truncate text-xs text-muted-foreground">{d.donorEmail}</span>
                    </TableCell>
                    <TableCell className="hidden max-w-48 truncate text-sm text-muted-foreground lg:table-cell">
                      {d.projectTitle ?? "Where most needed"}
                    </TableCell>
                    <TableCell className="hidden text-sm whitespace-nowrap text-muted-foreground sm:table-cell">
                      {dateFmt.format(new Date(d.createdAt))}
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>
      )}

      <nav
        aria-label="Pagination"
        className="mt-4 flex items-center justify-between text-sm text-muted-foreground"
      >
        <span>
          {total} {total === 1 ? "donation" : "donations"} · page {page} of {pageCount}
        </span>
        <span className="flex gap-2">
          {page > 1 ? (
            <Button asChild variant="outline" size="sm">
              <Link prefetch={false} href={`/admin/donations?${query(params, { page: page - 1 })}`}>
                Previous
              </Link>
            </Button>
          ) : null}
          {page < pageCount ? (
            <Button asChild variant="outline" size="sm">
              <Link prefetch={false} href={`/admin/donations?${query(params, { page: page + 1 })}`}>
                Next
              </Link>
            </Button>
          ) : null}
        </span>
      </nav>
    </>
  );
}
