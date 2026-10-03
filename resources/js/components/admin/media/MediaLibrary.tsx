"use client";

import { useEffect, useId, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Copy, ExternalLink, Trash2, TriangleAlert } from "lucide-react";
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { deleteMediaAction, getMediaUsageAction, updateMediaAction } from "@/lib/actions/media";
import type { MediaAssetDTO, MediaUsage } from "@/lib/content/media";
import { formatBytes } from "@/lib/content/media";
import { cn } from "@/lib/utils";
import { MediaThumb } from "./MediaThumb";
import { MediaUploader } from "./MediaUploader";

/** Media library grid + uploader + details sheet (alt text per language, usage, delete). */
export function MediaLibrary({
  items,
  canEdit,
  canDelete,
}: {
  items: MediaAssetDTO[];
  canEdit: boolean;
  canDelete: boolean;
}) {
  const router = useRouter();
  const [openId, setOpenId] = useState<string | null>(null);
  const open = items.find((i) => i.id === openId) ?? null;

  return (
    <div className="grid gap-6">
      <MediaUploader onAllDone={(uploaded) => uploaded.length && router.refresh()} />
      {items.length ? (
        <ul
          className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
          aria-label="Media files"
        >
          {items.map((a) => (
            <li key={a.id}>
              <button
                type="button"
                onClick={() => setOpenId(a.id)}
                className="group block w-full overflow-hidden rounded-xl border bg-card text-left outline-none hover:shadow-sm focus-visible:ring-3 focus-visible:ring-ring/50"
                aria-label={`Details for ${a.filename}`}
              >
                <MediaThumb asset={a} sizes="(min-width: 1024px) 16vw, 45vw" />
                <div className="p-2">
                  <p className="truncate text-xs font-medium">{a.filename}</p>
                  <p className="flex items-center gap-1 text-[11px] text-muted-foreground">
                    {formatBytes(a.size)}
                    {a.kind === "IMAGE" && !a.alt.it ? (
                      <Badge
                        variant="outline"
                        className="ml-auto h-4 border-amber-300 bg-amber-50 px-1 text-[10px] text-amber-900"
                      >
                        No alt
                      </Badge>
                    ) : null}
                  </p>
                </div>
              </button>
            </li>
          ))}
        </ul>
      ) : null}
      <Sheet open={!!open} onOpenChange={(o) => !o && setOpenId(null)}>
        <SheetContent className="w-full overflow-y-auto data-[side=right]:sm:max-w-lg">
          {open ? (
            <MediaDetails
              key={open.id}
              asset={open}
              canEdit={canEdit}
              canDelete={canDelete}
              onDeleted={() => {
                setOpenId(null);
                router.refresh();
              }}
              onSaved={() => router.refresh()}
            />
          ) : null}
        </SheetContent>
      </Sheet>
    </div>
  );
}

function MediaDetails({
  asset,
  canEdit,
  canDelete,
  onDeleted,
  onSaved,
}: {
  asset: MediaAssetDTO;
  canEdit: boolean;
  canDelete: boolean;
  onDeleted: () => void;
  onSaved: () => void;
}) {
  const id = useId();
  const [values, setValues] = useState({
    altIt: asset.alt.it,
    altEn: asset.alt.en,
    captionIt: asset.caption.it,
    captionEn: asset.caption.en,
    credit: asset.credit,
    focalX: asset.focalX,
    focalY: asset.focalY,
  });
  const [usage, setUsage] = useState<MediaUsage[] | null>(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();

  useEffect(() => {
    let cancelled = false;
    void getMediaUsageAction(asset.id).then((res) => {
      if (!cancelled) setUsage(res.ok ? res.data : []);
    });
    return () => {
      cancelled = true;
    };
  }, [asset.id]);

  const live = usage?.filter((u) => u.published) ?? [];
  const set = (k: keyof typeof values) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setValues((v) => ({ ...v, [k]: e.target.value }));
  const absoluteUrl = asset.url.startsWith("/")
    ? `${typeof window !== "undefined" ? window.location.origin : ""}${asset.url}`
    : asset.url;

  const save = () =>
    startTransition(async () => {
      const res = await updateMediaAction(asset.id, values);
      if (res.ok) {
        toast.success("Saved");
        onSaved();
      } else toast.error(res.message);
    });

  const remove = () =>
    startTransition(async () => {
      const res = await deleteMediaAction(asset.id);
      setConfirm(false);
      if (res.ok) {
        toast.success(res.message ?? "Deleted");
        onDeleted();
      } else toast.error(res.message);
    });

  return (
    <>
      <SheetHeader>
        <SheetTitle className="truncate pr-6">{asset.filename}</SheetTitle>
        <SheetDescription>
          {asset.mimeType} · {formatBytes(asset.size)}
          {asset.width && asset.height ? ` · ${asset.width}×${asset.height}px` : ""}
          {asset.uploadedBy ? ` · uploaded by ${asset.uploadedBy}` : ""}
        </SheetDescription>
      </SheetHeader>
      <div className="grid gap-5 px-4 pb-6">
        {asset.kind === "IMAGE" ? (
          <div className="grid gap-1">
            <div
              className={cn("relative overflow-hidden rounded-lg border", canEdit && "cursor-crosshair")}
              onClick={(e) => {
                if (!canEdit) return;
                const r = e.currentTarget.getBoundingClientRect();
                setValues((v) => ({
                  ...v,
                  focalX: Math.round(((e.clientX - r.left) / r.width) * 100) / 100,
                  focalY: Math.round(((e.clientY - r.top) / r.height) * 100) / 100,
                }));
              }}
            >
              <img src={asset.url} alt={asset.alt.it} className="block h-auto w-full" />
              {values.focalX !== null && values.focalY !== null ? (
                <span
                  aria-hidden
                  className="pointer-events-none absolute size-5 -translate-1/2 rounded-full border-2 border-white bg-primary/60 shadow"
                  style={{ left: `${values.focalX * 100}%`, top: `${values.focalY * 100}%` }}
                />
              ) : null}
            </div>
            {canEdit ? (
              <p className="text-xs text-muted-foreground">
                Click the image to set the focal point (kept visible when the image is cropped).
              </p>
            ) : null}
          </div>
        ) : (
          <a
            href={asset.url}
            target="_blank"
            rel="noopener"
            className="inline-flex items-center gap-1 text-sm underline"
          >
            Open PDF <ExternalLink className="size-3.5" aria-hidden />
          </a>
        )}

        <div className="flex gap-2">
          <Input readOnly value={absoluteUrl} aria-label="File URL" className="font-mono text-xs" />
          <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label="Copy URL"
            onClick={() =>
              navigator.clipboard?.writeText(absoluteUrl).then(() => toast.success("URL copied"))
            }
          >
            <Copy />
          </Button>
        </div>

        <fieldset className="grid min-w-0 gap-3" disabled={!canEdit}>
          <legend className="mb-1 text-sm font-semibold">Description</legend>
          <div className="grid gap-1">
            <Label htmlFor={`${id}-alt-it`}>Alt text — Italian{asset.kind === "IMAGE" ? " *" : ""}</Label>
            <Input id={`${id}-alt-it`} value={values.altIt} onChange={set("altIt")} maxLength={300} />
            <p className="text-xs text-muted-foreground">
              What does the image show? Read aloud by screen readers. Required before publishing.
            </p>
          </div>
          <div className="grid gap-1">
            <Label htmlFor={`${id}-alt-en`}>Alt text — English</Label>
            <Input id={`${id}-alt-en`} value={values.altEn} onChange={set("altEn")} maxLength={300} />
          </div>
          <div className="grid gap-1">
            <Label htmlFor={`${id}-cap-it`}>Caption — Italian</Label>
            <Input id={`${id}-cap-it`} value={values.captionIt} onChange={set("captionIt")} maxLength={300} />
          </div>
          <div className="grid gap-1">
            <Label htmlFor={`${id}-cap-en`}>Caption — English</Label>
            <Input id={`${id}-cap-en`} value={values.captionEn} onChange={set("captionEn")} maxLength={300} />
          </div>
          <div className="grid gap-1">
            <Label htmlFor={`${id}-credit`}>Credit / licence</Label>
            <Input id={`${id}-credit`} value={values.credit} onChange={set("credit")} maxLength={200} />
          </div>
          {canEdit ? (
            <Button type="button" onClick={save} disabled={pending} className="justify-self-start">
              {pending ? "Saving…" : "Save details"}
            </Button>
          ) : null}
        </fieldset>

        <section aria-labelledby={`${id}-usage`} className="grid gap-2">
          <h3 id={`${id}-usage`} className="text-sm font-semibold">
            Where it is used
          </h3>
          {usage === null ? (
            <p className="text-sm text-muted-foreground">Checking…</p>
          ) : usage.length === 0 ? (
            <p className="text-sm text-muted-foreground">Not used anywhere.</p>
          ) : (
            <ul className="grid gap-1 text-sm" data-testid="media-usage">
              {usage.map((u, i) => (
                <li key={i} className="flex items-center justify-between gap-2 rounded-md border px-2 py-1.5">
                  <span className="min-w-0">
                    {u.href ? (
                      <a href={u.href} className="font-medium underline-offset-2 hover:underline">
                        {u.label}
                      </a>
                    ) : (
                      <span className="font-medium">{u.label}</span>
                    )}
                    <span className="block text-xs text-muted-foreground">{u.where}</span>
                  </span>
                  <Badge variant={u.published ? "default" : "outline"}>
                    {u.published ? "Live" : "Draft"}
                  </Badge>
                </li>
              ))}
            </ul>
          )}
        </section>

        {canDelete ? (
          <div className="grid gap-2 border-t pt-4">
            {live.length ? (
              <p className="flex gap-2 text-sm text-amber-800">
                <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                Used by published content, so it cannot be deleted. Replace it there first.
              </p>
            ) : null}
            <Button
              type="button"
              variant="destructive"
              className="justify-self-start"
              disabled={pending || usage === null || live.length > 0}
              onClick={() => setConfirm(true)}
            >
              <Trash2 aria-hidden /> Delete file
            </Button>
          </div>
        ) : null}
      </div>

      <AlertDialog open={confirm} onOpenChange={setConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete “{asset.filename}”?</AlertDialogTitle>
            <AlertDialogDescription>
              {usage?.length
                ? `It is still referenced by ${usage.length} draft item(s); they will lose this file. `
                : ""}
              This cannot be undone.
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
    </>
  );
}
