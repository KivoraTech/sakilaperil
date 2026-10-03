import Link from "next/link";
import { Head } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatMoney } from "@/lib/payments/schemas";
import { statusLabel, statusTone } from "@/components/admin/engagement/labels";
import { ManualStatusSelect } from "@/components/admin/engagement/donation-controls";
import { PROVIDER_LABEL } from "@/components/admin/engagement/provider-labels";

type Titles = { locale: string; title: string }[] | null;
type DonationDetail = {
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
  message: string | null;
  locale: string;
  providerReference: string | null;
  providerData: Record<string, unknown>;
  createdAt: string;
  completedAt: string | null;
  project: Titles;
  campaign: Titles;
};

const dateFmt = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "full",
  timeStyle: "short",
  timeZone: "Europe/Rome",
});

/** Port of app/admin/(shell)/donations/[id]/page.tsx (data from DonationsController). */
export default function DonationDetailPage({
  donation: d,
  canManage,
}: {
  donation: DonationDetail;
  canManage: boolean;
}) {
  const data = d.providerData ?? {};
  const title = (list?: { locale: string; title: string }[] | null) =>
    list?.find((x) => x.locale === "it")?.title ?? list?.[0]?.title;

  const rows: [string, React.ReactNode][] = [
    ["Amount", <strong key="a">{formatMoney(d.amount, d.currency, "it")}</strong>],
    ["Frequency", data.frequency === "monthly" ? "Monthly (Stripe subscription)" : "One-off"],
    ["Method", PROVIDER_LABEL[d.provider]],
    ["Donor", d.donorName || "—"],
    [
      "Email",
      d.donorEmail ? (
        <a key="e" className="underline" href={`mailto:${encodeURIComponent(d.donorEmail)}`}>
          {d.donorEmail}
        </a>
      ) : (
        "—"
      ),
    ],
    ["Show name publicly", d.isAnonymous ? "No (anonymous)" : "Yes"],
    ["Project", title(d.project) ?? "Where most needed"],
    ["Campaign", title(d.campaign) ?? "—"],
    ["Message / note", d.message || "—"],
    ["Language", d.locale.toUpperCase()],
    ["Created", dateFmt.format(new Date(d.createdAt))],
    ["Completed", d.completedAt ? dateFmt.format(new Date(d.completedAt)) : "—"],
    [
      "Provider reference",
      <code key="r" className="text-xs break-all">
        {d.providerReference ?? "—"}
      </code>,
    ],
  ];
  // Minimal provider data (ids/status only — never card data).
  const providerEntries = Object.entries(data).filter(([k]) => k !== "frequency" && k !== "recordedById");

  return (
    <>
      <Head title="Donation" />
      <div className="mb-4">
        <Button asChild variant="ghost" size="sm">
          <Link prefetch={false} href="/admin/donations">
            <ArrowLeft aria-hidden="true" /> Back to donations
          </Link>
        </Button>
      </div>
      <AdminPageHeader
        title={d.reference}
        description={
          d.provider === "BANK_TRANSFER"
            ? "Manually recorded gift"
            : "Online donation — status set by the payment provider"
        }
      />
      <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section aria-label="Donation details" className="rounded-xl border bg-background p-5">
          <dl className="grid gap-3 sm:grid-cols-[11rem_1fr]">
            {rows.map(([label, value]) => (
              <div key={label} className="contents">
                <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
                <dd className="break-words">{value}</dd>
              </div>
            ))}
          </dl>
          {providerEntries.length ? (
            <details className="mt-6">
              <summary className="cursor-pointer text-sm font-medium">Provider data</summary>
              <dl className="mt-3 grid gap-2 text-xs sm:grid-cols-[11rem_1fr]">
                {providerEntries.map(([k, v]) => (
                  <div key={k} className="contents">
                    <dt className="text-muted-foreground">{k}</dt>
                    <dd className="font-mono break-all">{v === null ? "—" : String(v)}</dd>
                  </div>
                ))}
              </dl>
            </details>
          ) : null}
        </section>
        <aside className="flex flex-col gap-4 rounded-xl border bg-background p-5">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant={statusTone(d.status)}>{statusLabel(d.status)}</Badge>
            {d.isTestMode ? (
              <Badge variant="outline" className="border-amber-500 text-amber-700">
                TEST
              </Badge>
            ) : null}
          </div>
          {d.provider === "BANK_TRANSFER" && canManage ? (
            <ManualStatusSelect id={d.id} status={d.status} />
          ) : (
            <p className="text-sm text-muted-foreground">
              This status comes from the payment provider and cannot be edited. Refunds are made in the
              provider&apos;s dashboard and synced by webhook.
            </p>
          )}
        </aside>
      </div>
    </>
  );
}
