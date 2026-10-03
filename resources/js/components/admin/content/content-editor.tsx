"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useWatch, type FieldValues, type Path, type UseFormReturn } from "react-hook-form";
import {
  Archive,
  ArrowLeft,
  ExternalLink,
  Eye,
  EyeOff,
  MoreHorizontal,
  RotateCcw,
  Send,
  Trash2,
  TriangleAlert,
} from "lucide-react";
import { toast } from "sonner";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useUnsavedChangesWarning } from "@/components/admin/page-builder/use-unsaved-changes";
import { Form } from "@/components/ui/form";
import type { ActionResult } from "@/lib/actions/result";
import type { ContentStatusValue, SaveIntent } from "@/lib/content/common";
import { StatusBadge } from "./list-ui";

/**
 * Shared editor chrome for every content type: header with status, Save draft / Publish
 * (visually and behaviourally distinct), status menu (unpublish, archive, restore, delete
 * with confirmation), server field errors mapped onto inputs, toasts, and an unsaved-changes
 * warning. Permissions are re-checked by the server actions; the flags only hide controls.
 */
export type EditorPerms = { canEdit: boolean; canPublish: boolean; canDelete: boolean };

export function ContentEditor<T extends FieldValues>({
  form,
  id,
  kindLabel,
  title,
  basePath,
  status,
  publicUrl,
  previewPath,
  perms,
  readOnlyReason,
  onSave,
  onStatus,
  onDelete,
  main,
  sidebar,
}: {
  form: UseFormReturn<T>;
  id: string | null;
  /** e.g. "post", "project" */
  kindLabel: string;
  title: string;
  basePath: string;
  status: ContentStatusValue | null;
  publicUrl?: string | null;
  /** Authenticated draft preview (e.g. /preview/it/news/<id>); shown for items that are not live. */
  previewPath?: string | null;
  perms: EditorPerms;
  readOnlyReason?: string;
  onSave: (values: T, intent: SaveIntent) => Promise<ActionResult<{ id: string }>>;
  onStatus?: (status: ContentStatusValue) => Promise<ActionResult<{ id: string }>>;
  onDelete?: () => Promise<ActionResult<{ id: string }>>;
  main: React.ReactNode;
  sidebar: React.ReactNode;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [confirmDelete, setConfirmDelete] = useState(false);
  const { isDirty } = form.formState;

  // Warns on reload/close AND on in-app links (e.g. the back arrow), not just the browser.
  useUnsavedChangesWarning(isDirty && !pending);

  const applyResult = (res: ActionResult<{ id: string }>, values?: T) => {
    if (res.ok) {
      toast.success(res.message ?? "Saved");
      if (values) form.reset(values);
      if (!id) router.replace(`${basePath}/${res.data.id}`);
      else router.refresh();
      return;
    }
    toast.error(res.message);
    for (const [key, messages] of Object.entries(res.fieldErrors ?? {})) {
      if (messages?.[0]) form.setError(key as Path<T>, { type: "server", message: messages[0] });
    }
  };

  const save = (intent: SaveIntent) =>
    form.handleSubmit(
      (values) =>
        new Promise<void>((resolve) =>
          startTransition(async () => {
            applyResult(await onSave(values, intent), values);
            resolve();
          }),
        ),
      () => toast.error("Please check the highlighted fields."),
    )();

  const changeStatus = (next: ContentStatusValue) => {
    if (!onStatus) return;
    if (isDirty) {
      toast.error("Save your changes first.");
      return;
    }
    startTransition(async () => applyResult(await onStatus(next)));
  };

  /** Opens the draft preview in a new tab, saving unsaved changes first (drafts only). */
  const preview = () => {
    if (!previewPath) return;
    // Open synchronously (inside the click) so pop-up blockers allow it; navigate once saved.
    const win = window.open("about:blank", "_blank");
    const go = () => {
      if (win) {
        win.opener = null;
        win.location.href = previewPath;
      } else {
        toast("Your browser blocked the preview tab.", {
          action: { label: "Open preview", onClick: () => window.open(previewPath, "_blank", "noopener") },
        });
      }
    };
    if (!isDirty) return go();
    void form.handleSubmit(
      (values) =>
        new Promise<void>((resolve) =>
          startTransition(async () => {
            const res = await onSave(values, "save");
            if (res.ok) {
              form.reset(values);
              router.refresh();
              go();
            } else {
              win?.close();
              applyResult(res);
            }
            resolve();
          }),
        ),
      () => {
        win?.close();
        toast.error("Please check the highlighted fields before previewing.");
      },
    )();
  };

  const remove = () =>
    startTransition(async () => {
      if (!onDelete) return;
      const res = await onDelete();
      setConfirmDelete(false);
      if (res.ok) {
        form.reset(form.getValues());
        toast.success(res.message ?? "Deleted");
        router.replace(basePath);
      } else toast.error(res.message);
    });

  const isPublished = status === "PUBLISHED";
  // "Published" with a future publication date is not public yet (the public queries hide it).
  const publishedAt: unknown = useWatch({ control: form.control, name: "publishedAt" as Path<T> });
  const [loadedAt] = useState(() => Date.now());
  const scheduledFor =
    isPublished && typeof publishedAt === "string" && publishedAt !== "" && Date.parse(publishedAt) > loadedAt
      ? new Date(publishedAt)
      : null;
  const isLive = isPublished && !scheduledFor;
  const menuItems = id && (perms.canPublish || perms.canDelete);

  return (
    <Form {...form}>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void save("save");
        }}
        noValidate
        aria-busy={pending}
      >
        <div className="sticky top-14 z-20 -mx-4 -mt-4 mb-6 flex flex-wrap items-center gap-3 border-b bg-background/95 px-4 py-3 backdrop-blur md:-mx-8 md:-mt-8 md:px-8">
          <Button asChild variant="ghost" size="icon-sm" aria-label="Back to list">
            <Link prefetch={false} href={basePath}>
              <ArrowLeft />
            </Link>
          </Button>
          <div className="mr-auto flex min-w-0 items-center gap-2">
            <h1 className="truncate text-lg font-semibold tracking-tight">{title}</h1>
            {status ? <StatusBadge status={status} scheduled={!!scheduledFor} /> : null}
            {isDirty ? <span className="text-xs text-muted-foreground">Unsaved changes</span> : null}
          </div>
          {publicUrl && isLive ? (
            <Button asChild variant="ghost" size="sm">
              <a href={publicUrl} target="_blank" rel="noopener">
                <ExternalLink aria-hidden /> View on website
              </a>
            </Button>
          ) : null}
          {previewPath && id && !isLive ? (
            <Button type="button" variant="ghost" size="sm" onClick={preview} disabled={pending}>
              <Eye aria-hidden /> Preview
            </Button>
          ) : null}
          {perms.canEdit ? (
            <>
              <Button
                type="submit"
                variant={isPublished ? "default" : "outline"}
                disabled={pending}
                data-testid="save"
              >
                {pending ? "Saving…" : isPublished ? "Save changes" : "Save draft"}
              </Button>
              {!isPublished && perms.canPublish ? (
                <Button
                  type="button"
                  onClick={() => void save("publish")}
                  disabled={pending}
                  data-testid="publish"
                >
                  <Send aria-hidden /> Publish
                </Button>
              ) : null}
            </>
          ) : null}
          {menuItems ? (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  aria-label="More actions"
                  disabled={pending}
                >
                  <MoreHorizontal />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                {perms.canPublish && status === "PUBLISHED" ? (
                  <DropdownMenuItem onSelect={() => changeStatus("DRAFT")}>
                    <EyeOff aria-hidden /> Unpublish (back to draft)
                  </DropdownMenuItem>
                ) : null}
                {perms.canPublish && status === "ARCHIVED" ? (
                  <DropdownMenuItem onSelect={() => changeStatus("DRAFT")}>
                    <RotateCcw aria-hidden /> Restore as draft
                  </DropdownMenuItem>
                ) : null}
                {perms.canPublish && status !== "ARCHIVED" ? (
                  <DropdownMenuItem onSelect={() => changeStatus("ARCHIVED")}>
                    <Archive aria-hidden /> Archive
                  </DropdownMenuItem>
                ) : null}
                {perms.canDelete ? (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant="destructive" onSelect={() => setConfirmDelete(true)}>
                      <Trash2 aria-hidden /> Delete…
                    </DropdownMenuItem>
                  </>
                ) : null}
              </DropdownMenuContent>
            </DropdownMenu>
          ) : null}
        </div>

        {isLive && perms.canEdit ? (
          <p className="mb-4 flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
            This {kindLabel} is live. Saved changes appear on the website immediately.
          </p>
        ) : null}
        {scheduledFor ? (
          <p className="mb-4 flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
            This {kindLabel} is scheduled: it will appear on the website on{" "}
            {new Intl.DateTimeFormat("en-GB", { dateStyle: "long", timeStyle: "short" }).format(scheduledFor)}
            . Until then visitors cannot see it. To publish it now, clear or change the publication date.
          </p>
        ) : null}
        {!perms.canEdit ? (
          <p className="mb-4 rounded-lg border bg-muted/40 px-3 py-2 text-sm">
            {readOnlyReason ?? `You can view this ${kindLabel} but not change it.`}
          </p>
        ) : null}

        <fieldset
          disabled={!perms.canEdit || pending}
          // A fieldset has `min-inline-size: min-content`; without min-w-0 the grid cannot shrink below its
          // widest child (a long file name in the cover row) and the page scrolls sideways.
          className="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
        >
          <div className="grid min-w-0 grid-cols-[minmax(0,1fr)] content-start gap-6">{main}</div>
          <aside className="grid min-w-0 grid-cols-[minmax(0,1fr)] content-start gap-5" aria-label="Settings">
            {sidebar}
          </aside>
        </fieldset>
      </form>

      <AlertDialog open={confirmDelete} onOpenChange={setConfirmDelete}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this {kindLabel}?</AlertDialogTitle>
            <AlertDialogDescription>
              Both language versions are removed permanently. Files stay in the media library. To hide it but
              keep it, archive it instead.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={remove}>
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Form>
  );
}

/** Card-like section used inside editors. */
export function EditorSection({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4 rounded-xl border bg-card p-4 sm:p-5">
      <div>
        <h2 className="text-sm font-semibold">{title}</h2>
        {description ? <p className="mt-0.5 text-xs text-muted-foreground">{description}</p> : null}
      </div>
      {children}
    </section>
  );
}

/** Collapsed-by-default card for rarely needed fields, so everyday forms stay short. */
export function EditorAdvanced({
  title = "Advanced",
  description,
  children,
}: {
  title?: string;
  description?: string;
  children: React.ReactNode;
}) {
  return (
    <details className="group rounded-xl border bg-card p-4 sm:p-5">
      <summary className="cursor-pointer text-sm font-semibold outline-none focus-visible:ring-3 focus-visible:ring-ring/50">
        {title}
        {description ? (
          <span className="mt-0.5 block text-xs font-normal text-muted-foreground">{description}</span>
        ) : null}
      </summary>
      <div className="mt-4 grid gap-4">{children}</div>
    </details>
  );
}
