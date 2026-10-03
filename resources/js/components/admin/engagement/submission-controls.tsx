"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Trash2 } from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { ActionResult } from "@/lib/actions/result";
import {
  deleteSubmission,
  markSubmissionRead,
  saveInternalNote,
  setSubmissionStatus,
} from "@/lib/actions/engagement";

type Type = "contact" | "volunteer" | "membership" | "partner";

function report(res: ActionResult, success?: string) {
  if (res.ok) {
    if (success ?? res.message) toast.success(success ?? res.message);
  } else toast.error(res.message);
  return res.ok;
}

/** Marks a NEW item as read once when the detail view opens (client-side, so link prefetch never does). */
export function AutoMarkRead({ type, id, isNew }: { type: Type; id: string; isNew: boolean }) {
  const router = useRouter();
  const done = useRef(false);
  useEffect(() => {
    if (!isNew || done.current) return;
    done.current = true;
    markSubmissionRead({ type, id, read: true }).then((r) => r.ok && router.refresh());
  }, [type, id, isNew, router]);
  return null;
}

export function MarkUnreadButton({ type, id }: { type: Type; id: string }) {
  const [pending, start] = useTransition();
  const router = useRouter();
  return (
    <Button
      variant="outline"
      size="sm"
      disabled={pending}
      onClick={() =>
        start(async () => {
          if (report(await markSubmissionRead({ type, id, read: false }), "Marked as unread")) {
            router.push(`/admin/submissions?type=${type}`);
          }
        })
      }
    >
      Mark as unread
    </Button>
  );
}

export function StatusSelect({
  type,
  id,
  status,
  options,
}: {
  type: Type;
  id: string;
  status: string;
  options: { value: string; label: string }[];
}) {
  const [value, setValue] = useState(status);
  const [pending, start] = useTransition();
  const router = useRouter();
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={`status-${id}`}>Status</Label>
      <Select
        value={value}
        disabled={pending}
        onValueChange={(next) => {
          const prev = value;
          setValue(next);
          start(async () => {
            const okay = report(await setSubmissionStatus({ type, id, status: next }));
            if (!okay) setValue(prev);
            else router.refresh();
          });
        }}
      >
        <SelectTrigger id={`status-${id}`} className="w-48">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((o) => (
            <SelectItem key={o.value} value={o.value}>
              {o.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

export function NoteForm({ type, id, note }: { type: Type; id: string; note: string }) {
  const [value, setValue] = useState(note);
  const [pending, start] = useTransition();
  return (
    <form
      className="flex flex-col gap-2"
      onSubmit={(e) => {
        e.preventDefault();
        start(async () => {
          report(await saveInternalNote({ type, id, note: value }));
        });
      }}
    >
      <Label htmlFor={`note-${id}`}>Internal note (team only)</Label>
      <Textarea
        id={`note-${id}`}
        rows={4}
        maxLength={5000}
        value={value}
        onChange={(e) => setValue(e.target.value)}
      />
      <div>
        <Button type="submit" size="sm" disabled={pending || value === note}>
          {pending ? "Saving…" : "Save note"}
        </Button>
      </div>
    </form>
  );
}

export function DeleteSubmissionButton({
  type,
  id,
  label,
  redirectTo,
  size = "sm",
}: {
  type: Type | "newsletter";
  id: string;
  label: string;
  redirectTo?: string;
  size?: "sm" | "icon-sm";
}) {
  const [pending, start] = useTransition();
  const router = useRouter();
  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button
          variant="destructive"
          size={size}
          aria-label={size === "icon-sm" ? `Delete ${label}` : undefined}
        >
          <Trash2 aria-hidden="true" />
          {size === "icon-sm" ? null : "Delete permanently"}
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete permanently?</AlertDialogTitle>
          <AlertDialogDescription>
            This erases the personal data of {label} (GDPR right to erasure). It cannot be undone. The audit
            log keeps only that an item was deleted, not its content.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction
            disabled={pending}
            onClick={() =>
              start(async () => {
                if (report(await deleteSubmission({ type, id }))) {
                  if (redirectTo) router.push(redirectTo);
                  else router.refresh();
                }
              })
            }
          >
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
