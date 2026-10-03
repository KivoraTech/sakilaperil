import Link from "next/link";
import { Head } from "@inertiajs/react";
import { ArrowLeft, Mail } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  applicationStatusOptions,
  contactStatusOptions,
  statusLabel,
  statusTone,
} from "@/components/admin/engagement/labels";
import {
  AutoMarkRead,
  DeleteSubmissionButton,
  MarkUnreadButton,
  NoteForm,
  StatusSelect,
} from "@/components/admin/engagement/submission-controls";

type SubmissionType = "contact" | "volunteer" | "membership" | "partner";

const TYPE_LABEL: Record<SubmissionType, string> = {
  contact: "Contact message",
  volunteer: "Volunteer application",
  membership: "Membership application",
  partner: "Partner enquiry",
};

/** Field order + labels per type. Everything is rendered as plain text (React escapes it). */
const FIELDS: Record<SubmissionType, [string, string][]> = {
  contact: [
    ["name", "Name"],
    ["email", "Email"],
    ["phone", "Phone"],
    ["subject", "Topic"],
    ["message", "Message"],
  ],
  volunteer: [
    ["firstName", "First name"],
    ["lastName", "Last name"],
    ["email", "Email"],
    ["phone", "Phone"],
    ["city", "City"],
    ["country", "Country"],
    ["skills", "Skills"],
    ["availability", "Availability"],
    ["message", "Message"],
  ],
  membership: [
    ["firstName", "First name"],
    ["lastName", "Last name"],
    ["email", "Email"],
    ["phone", "Phone"],
    ["membershipType", "Membership type"],
    ["address", "Address"],
    ["city", "City"],
    ["country", "Country"],
    ["message", "Message"],
  ],
  partner: [
    ["organization", "Organisation"],
    ["contactName", "Contact person"],
    ["email", "Email"],
    ["phone", "Phone"],
    ["website", "Website"],
    ["partnershipType", "Partnership type"],
    ["message", "Message"],
  ],
};

const dateFmt = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "full",
  timeStyle: "short",
  timeZone: "Europe/Rome",
});

/** Port of app/admin/(shell)/submissions/[type]/[id]/page.tsx (data from SubmissionsController). */
export default function SubmissionDetailPage({
  type,
  row,
  handledBy,
}: {
  type: SubmissionType;
  row: Record<string, unknown>;
  handledBy: { name: string } | null;
}) {
  const t = type;
  const id = String(row.id);

  const str = (k: string) => (typeof row[k] === "string" ? (row[k] as string) : "");
  const status = str("status");
  const email = str("email");
  const title =
    t === "contact"
      ? str("name")
      : t === "partner"
        ? str("organization")
        : `${str("firstName")} ${str("lastName")}`;
  const backHref = `/admin/submissions?type=${t}`;

  return (
    <>
      <Head title="Submission" />
      <AutoMarkRead type={t} id={id} isNew={status === "NEW"} />
      <div className="mb-4">
        <Button asChild variant="ghost" size="sm">
          <Link prefetch={false} href={backHref}>
            <ArrowLeft aria-hidden="true" /> Back to inbox
          </Link>
        </Button>
      </div>
      <AdminPageHeader
        title={title || TYPE_LABEL[t]}
        description={`${TYPE_LABEL[t]} · received ${dateFmt.format(new Date(String(row.createdAt)))}`}
        actions={
          <>
            {email ? (
              <Button asChild size="sm">
                <a href={`mailto:${encodeURIComponent(email)}`}>
                  <Mail aria-hidden="true" /> Reply by email
                </a>
              </Button>
            ) : null}
            <MarkUnreadButton type={t} id={id} />
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section aria-label="Submission details" className="rounded-xl border bg-background p-5">
          <dl className="grid gap-4 sm:grid-cols-[10rem_1fr]">
            {FIELDS[t].map(([key, label]) => {
              const v = str(key);
              return (
                <div key={key} className="contents">
                  <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
                  <dd
                    className={
                      key === "message" || key === "skills"
                        ? "break-words whitespace-pre-wrap"
                        : "break-words"
                    }
                  >
                    {v || <span className="text-muted-foreground">—</span>}
                  </dd>
                </div>
              );
            })}
            <dt className="text-sm font-medium text-muted-foreground">Language</dt>
            <dd className="uppercase">{str("locale")}</dd>
            <dt className="text-sm font-medium text-muted-foreground">Privacy consent</dt>
            <dd>{dateFmt.format(new Date(String(row.consentAt)))}</dd>
          </dl>
        </section>

        <aside className="flex flex-col gap-5 rounded-xl border bg-background p-5">
          <div className="flex items-center gap-2">
            <Badge variant={statusTone(status)}>{statusLabel(status)}</Badge>
            {handledBy ? (
              <span className="text-xs text-muted-foreground">last handled by {handledBy.name}</span>
            ) : null}
          </div>
          <StatusSelect
            type={t}
            id={id}
            status={status}
            options={t === "contact" ? contactStatusOptions : applicationStatusOptions}
          />
          <NoteForm type={t} id={id} note={str("internalNote")} />
          <div className="border-t pt-4">
            <DeleteSubmissionButton type={t} id={id} label={title || "this person"} redirectTo={backHref} />
          </div>
        </aside>
      </div>
    </>
  );
}
