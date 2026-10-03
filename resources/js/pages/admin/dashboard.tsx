import Link from "next/link";
import {
  AlertTriangle,
  CalendarDays,
  CalendarPlus,
  ExternalLink,
  FilePen,
  FileText,
  FolderKanban,
  FolderPlus,
  HandCoins,
  House,
  ImageIcon,
  ImageUp,
  Inbox,
  Newspaper,
  PenLine,
  ScrollText,
  type LucideIcon,
} from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Head } from "@inertiajs/react";
import { can } from "@/lib/permissions";
import type { DashboardData } from "@/lib/queries/admin/dashboard";
import { GettingStarted } from "./dashboard/getting-started";
import { MissingPages } from "./dashboard/missing-pages";

const dt = (iso: string, style: "short" | "medium" = "medium") =>
  new Intl.DateTimeFormat("en-GB", { dateStyle: style, timeStyle: "short", timeZone: "Europe/Rome" }).format(
    new Date(iso),
  );

const money = (minor: number, currency: string) =>
  new Intl.NumberFormat("it-IT", { style: "currency", currency }).format(minor / 100);

function ActionTile({
  href,
  icon: Icon,
  title,
  description,
  external,
}: {
  href: string;
  icon: LucideIcon;
  title: string;
  description: string;
  external?: boolean;
}) {
  const className =
    "group flex h-full items-start gap-3 rounded-xl border bg-background p-4 shadow-xs outline-none transition-colors hover:border-primary/50 hover:bg-primary/5 focus-visible:ring-3 focus-visible:ring-ring/50";
  const body = (
    <>
      <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <Icon className="size-5" aria-hidden />
      </span>
      <span className="min-w-0">
        <span className="block font-medium">
          {title}
          {external ? <span className="sr-only"> (opens in a new tab)</span> : null}
        </span>
        <span className="block text-sm text-muted-foreground">{description}</span>
      </span>
    </>
  );
  return external ? (
    <a href={href} target="_blank" rel="noopener noreferrer" className={className}>
      {body}
    </a>
  ) : (
    <Link prefetch={false} href={href} className={className}>
      {body}
    </Link>
  );
}

function Stat({
  label,
  value,
  href,
  icon: Icon,
  detail,
}: {
  label: string;
  value: number | string;
  href?: string;
  icon: LucideIcon;
  detail?: string;
}) {
  const body = (
    <Card className="h-full transition-colors hover:border-primary/40">
      <CardHeader>
        <CardDescription className="flex items-center gap-2">
          <Icon className="size-4" aria-hidden /> {label}
        </CardDescription>
        <CardTitle className="text-3xl tabular-nums">{value}</CardTitle>
        {detail ? <p className="text-xs text-muted-foreground">{detail}</p> : null}
      </CardHeader>
    </Card>
  );
  return href ? (
    <Link
      prefetch={false}
      href={href}
      className="rounded-xl outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
    >
      {body}
    </Link>
  ) : (
    body
  );
}

/** Dashboard — real counts and recent activity only (PLAN.md §6). */
export default function DashboardPage({ data: d }: { data: DashboardData }) {
  const firstName = d.user.name.split(" ")[0];

  const role = d.user.role;
  const actions: {
    href: string;
    icon: LucideIcon;
    title: string;
    description: string;
    external?: boolean;
  }[] = [];
  if (can(role, "content.create"))
    actions.push({
      href: "/admin/news/new",
      icon: PenLine,
      title: "Write a news story",
      description: "Share an update from Nepal or Italy.",
    });
  if (can(role, "content.create"))
    actions.push({
      href: "/admin/events/new",
      icon: CalendarPlus,
      title: "Add an event",
      description: "A dinner, a talk, a fundraiser…",
    });
  if (can(role, "content.create"))
    actions.push({
      href: "/admin/projects/new",
      icon: FolderPlus,
      title: "Add a project",
      description: "A school, a clinic, a campaign.",
    });
  if (can(role, "media.upload"))
    actions.push({
      href: "/admin/media",
      icon: ImageUp,
      title: "Upload photos",
      description: "Add pictures to use anywhere on the site.",
    });
  if (d.homePageId && can(role, "pages.editDraft"))
    actions.push({
      href: `/admin/pages/${d.homePageId}`,
      icon: House,
      title: "Edit the home page",
      description: "The first page visitors see.",
    });
  if (can(role, "pages.view"))
    actions.push({
      href: "/admin/pages",
      icon: FilePen,
      title: "Edit a page",
      description: "About us, Get involved, Contacts…",
    });
  if (can(role, "pages.create"))
    actions.push({
      href: "/admin/pages?new=1",
      icon: FileText,
      title: "Create a new page",
      description: "Start a page from ready-made blocks.",
    });
  actions.push({
    href: "/it",
    icon: ExternalLink,
    title: "View website",
    description: "See the site as visitors do.",
    external: true,
  });

  const attention: { text: string; href: string }[] = [];
  if (d.pages.withChanges)
    attention.push({
      text: `${d.pages.withChanges} live page(s) have unpublished changes`,
      href: "/admin/pages?status=changes",
    });
  if (d.submissions?.total)
    attention.push({
      text: `${d.submissions.total} new form submission(s) to review`,
      href: "/admin/submissions",
    });
  if (d.donations?.pendingBank)
    attention.push({
      text: `${d.donations.pendingBank} bank transfer pledge(s) to reconcile`,
      href: "/admin/donations",
    });
  if (d.todoCount)
    attention.push({
      text: `${d.todoCount} organisation detail(s) still to fill in (hidden on the website until then)`,
      href: "/admin/settings",
    });

  return (
    <>
      <Head title="Dashboard" />
      <AdminPageHeader title={`Namaste, ${firstName}`} description="What would you like to do today?" />

      <section aria-labelledby="quick-actions" className="mb-6">
        <h2 id="quick-actions" className="sr-only">
          Quick actions
        </h2>
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {actions.map((a) => (
            <li key={a.href}>
              <ActionTile {...a} />
            </li>
          ))}
        </ul>
      </section>

      <MissingPages keys={d.missingSystemPages ?? []} />

      <GettingStarted items={d.checklist} />

      {attention.length ? (
        <section
          aria-labelledby="attention"
          className="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950"
        >
          <h2 id="attention" className="flex items-center gap-2 font-semibold">
            <AlertTriangle className="size-4" aria-hidden /> Needs attention
          </h2>
          <ul className="mt-2 grid gap-1 text-sm">
            {attention.map((a) => (
              <li key={a.href}>
                <Link
                  prefetch={false}
                  href={a.href}
                  className="underline underline-offset-4 hover:no-underline"
                >
                  {a.text}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat
          label="Live pages"
          value={d.pages.published}
          detail={`${d.pages.draft} draft${d.pages.draft === 1 ? "" : "s"}`}
          href="/admin/pages"
          icon={FileText}
        />
        <Stat
          label="Published news"
          value={d.news.published}
          detail={`${d.news.drafts} draft${d.news.drafts === 1 ? "" : "s"}`}
          href="/admin/news"
          icon={Newspaper}
        />
        <Stat
          label="Published projects"
          value={d.projectsActive}
          detail={`${d.events.length} upcoming event${d.events.length === 1 ? "" : "s"}`}
          href="/admin/projects"
          icon={FolderKanban}
        />
        {d.submissions ? (
          <Stat
            label="New submissions"
            value={d.submissions.total}
            detail={`${d.submissions.contact} messages · ${d.submissions.volunteer} volunteers · ${d.submissions.membership} members · ${d.submissions.partner} partners`}
            href="/admin/submissions"
            icon={Inbox}
          />
        ) : (
          <Stat label="Photos & files" value={d.mediaCount} href="/admin/media" icon={ImageIcon} />
        )}
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <FilePen className="size-4" aria-hidden /> Continue where you left off
            </CardTitle>
            <CardDescription>Drafts and unpublished changes, most recent first.</CardDescription>
          </CardHeader>
          <CardContent>
            {d.drafts.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No drafts waiting. Everything you started is live.
              </p>
            ) : (
              <ul className="divide-y">
                {d.drafts.map((x) => (
                  <li key={x.key} className="flex items-center justify-between gap-3 py-2 text-sm">
                    <span className="min-w-0">
                      <span className="block truncate font-medium">{x.title}</span>
                      <span className="text-xs text-muted-foreground">
                        {x.kind} · {x.isLive ? "live, with unpublished changes" : "draft, not live"} · edited{" "}
                        {dt(x.updatedAt, "short")}
                      </span>
                    </span>
                    <Button asChild size="sm" variant="outline" className="shrink-0">
                      <Link prefetch={false} href={x.href} aria-label={`Continue editing ${x.title}`}>
                        Continue
                      </Link>
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Newspaper className="size-4" aria-hidden /> Recent news & stories
            </CardTitle>
          </CardHeader>
          <CardContent>
            {d.news.recent.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No news yet.{" "}
                <Link prefetch={false} href="/admin/news/new" className="underline underline-offset-4">
                  Write the first story
                </Link>
              </p>
            ) : (
              <ul className="divide-y">
                {d.news.recent.map((n) => (
                  <li key={n.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                    <Link
                      prefetch={false}
                      href={`/admin/news/${n.id}`}
                      className="min-w-0 truncate hover:underline"
                    >
                      {n.title}
                    </Link>
                    <span className="flex shrink-0 items-center gap-2">
                      <Badge variant={n.status === "PUBLISHED" ? "default" : "secondary"}>
                        {n.status === "PUBLISHED"
                          ? "live"
                          : n.status === "DRAFT"
                            ? "draft"
                            : n.status.toLowerCase()}
                      </Badge>
                      <span className="hidden text-xs text-muted-foreground sm:inline">
                        {dt(n.updatedAt, "short")}
                      </span>
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        {d.donations ? (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <HandCoins className="size-4" aria-hidden /> Recent donations
              </CardTitle>
              <CardDescription>
                This month (live payments only):{" "}
                {d.donations.month.length
                  ? d.donations.month.map((m) => `${money(m.amount, m.currency)} from ${m.count}`).join(" · ")
                  : "none yet"}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {d.donations.recent.length === 0 ? (
                <p className="text-sm text-muted-foreground">No completed donations yet.</p>
              ) : (
                <ul className="divide-y">
                  {d.donations.recent.map((x) => (
                    <li key={x.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                      <span className="min-w-0 truncate">
                        {x.isAnonymous || !x.donorName ? "Anonymous" : x.donorName}{" "}
                        <span className="text-xs text-muted-foreground">
                          · {x.provider.toLowerCase().replace("_", " ")}
                        </span>
                        {x.isTestMode ? (
                          <Badge variant="outline" className="ml-2">
                            test
                          </Badge>
                        ) : null}
                      </span>
                      <span className="shrink-0 font-medium tabular-nums">{money(x.amount, x.currency)}</span>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        ) : (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <CalendarDays className="size-4" aria-hidden /> Upcoming events
              </CardTitle>
            </CardHeader>
            <CardContent>
              {d.events.length === 0 ? (
                <p className="text-sm text-muted-foreground">No upcoming events.</p>
              ) : (
                <ul className="divide-y">
                  {d.events.map((e) => (
                    <li key={e.id} className="flex justify-between gap-3 py-2 text-sm">
                      <Link
                        prefetch={false}
                        href={`/admin/events/${e.id}`}
                        className="truncate hover:underline"
                      >
                        {e.title}
                      </Link>
                      <span className="shrink-0 text-xs text-muted-foreground">
                        {dt(e.startsAt, "short")}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        )}

        {d.activity ? (
          <Card className="lg:col-span-2">
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="flex items-center gap-2 text-base">
                <ScrollText className="size-4" aria-hidden /> Recent activity
              </CardTitle>
              <Link prefetch={false} href="/admin/audit" className="text-sm underline underline-offset-4">
                Full audit log
              </Link>
            </CardHeader>
            <CardContent>
              {d.activity.length === 0 ? (
                <p className="text-sm text-muted-foreground">Nothing recorded yet.</p>
              ) : (
                <ul className="divide-y">
                  {d.activity.map((a) => (
                    <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                      <span>
                        <strong className="font-medium">{a.user ?? "System"}</strong> {a.description}
                      </span>
                      <time className="text-xs text-muted-foreground" dateTime={a.createdAt}>
                        {dt(a.createdAt, "short")}
                      </time>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        ) : null}
      </div>
    </>
  );
}
