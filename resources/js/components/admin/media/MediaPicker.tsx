"use client";

import { useEffect, useId, useState, useTransition } from "react";
import { Check, Search } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { searchMediaAction, updateMediaAction } from "@/lib/actions/media";
import type { MediaAssetDTO } from "@/lib/content/media";
import { formatBytes } from "@/lib/content/media";
import { cn } from "@/lib/utils";
import { MediaThumb } from "./MediaThumb";
import { MediaUploader } from "./MediaUploader";

/**
 * Reusable media picker dialog (exported for the BUILDER track's block forms too).
 *
 *   const [open, setOpen] = useState(false);
 *   <MediaPicker open={open} onOpenChange={setOpen} accept="IMAGE" onSelect={(asset) => …} />
 *
 * - Browse/search the library (paged) or upload new files (drag & drop, multi).
 * - For images without Italian alt text, the picker asks for it before selecting
 *   (publishing is blocked for images without alt text).
 */
export type MediaPickerProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSelect: (asset: MediaAssetDTO) => void;
  /** Restrict to images or documents (PDF). */
  accept?: "IMAGE" | "DOCUMENT";
  title?: string;
};

const PAGE_SIZE = 24;

export function MediaPicker({ open, onOpenChange, onSelect, accept, title }: MediaPickerProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[90vh] flex-col gap-3 sm:max-w-4xl" data-testid="media-picker">
        <DialogHeader>
          <DialogTitle>{title ?? (accept === "DOCUMENT" ? "Choose a PDF" : "Choose an image")}</DialogTitle>
          <DialogDescription>Pick a file from the media library or upload a new one.</DialogDescription>
        </DialogHeader>
        {/* Mounted only while open: search/selection state resets on every open. */}
        {open ? (
          <PickerBody
            accept={accept}
            onCancel={() => onOpenChange(false)}
            onChoose={(a) => {
              onSelect(a);
              onOpenChange(false);
            }}
          />
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

type Page = { key: string; items: MediaAssetDTO[]; page: number; pageCount: number; error: string | null };

function PickerBody({
  accept,
  onCancel,
  onChoose,
}: {
  accept?: "IMAGE" | "DOCUMENT";
  onCancel: () => void;
  onChoose: (asset: MediaAssetDTO) => void;
}) {
  const [tab, setTab] = useState<"library" | "upload">("library");
  const [q, setQ] = useState("");
  const [query, setQuery] = useState("");
  const [reload, setReload] = useState(0);
  const [result, setResult] = useState<Page | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  const [selected, setSelected] = useState<MediaAssetDTO | null>(null);
  const requestKey = `${query}|${reload}`;
  const loading = result?.key !== requestKey;

  // Debounce typing.
  useEffect(() => {
    const t = setTimeout(() => setQuery(q), 250);
    return () => clearTimeout(t);
  }, [q]);

  // Fetch the first page for the current query (state is only set in the async callback).
  useEffect(() => {
    let cancelled = false;
    void searchMediaAction({ q: query, kind: accept ?? "", page: 1, pageSize: PAGE_SIZE }).then((res) => {
      if (cancelled) return;
      setResult(
        res.ok
          ? {
              key: requestKey,
              items: res.data.items,
              page: res.data.page,
              pageCount: res.data.pageCount,
              error: null,
            }
          : { key: requestKey, items: [], page: 1, pageCount: 1, error: res.message },
      );
    });
    return () => {
      cancelled = true;
    };
  }, [query, accept, requestKey]);

  const loadMore = async () => {
    if (!result) return;
    setLoadingMore(true);
    const res = await searchMediaAction({
      q: query,
      kind: accept ?? "",
      page: result.page + 1,
      pageSize: PAGE_SIZE,
    });
    setLoadingMore(false);
    if (res.ok)
      setResult((prev) =>
        prev
          ? {
              ...prev,
              items: [...prev.items, ...res.data.items],
              page: res.data.page,
              pageCount: res.data.pageCount,
            }
          : prev,
      );
    else toast.error(res.message);
  };

  const items = result?.items ?? [];
  const error = result?.error ?? null;

  return (
    <>
      <Tabs value={tab} onValueChange={(v) => setTab(v as "library" | "upload")} className="min-h-0 flex-1">
        <TabsList>
          <TabsTrigger value="library">Library</TabsTrigger>
          <TabsTrigger value="upload">Upload</TabsTrigger>
        </TabsList>
        <TabsContent value="library" className="flex min-h-0 flex-col gap-3">
          <div className="relative">
            <Search
              className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
              aria-hidden
            />
            <Input
              type="search"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Search by file name or alt text"
              aria-label="Search media"
              className="pl-8"
            />
          </div>
          <div
            className="min-h-[240px] overflow-y-auto pr-1"
            style={{ maxHeight: "50vh" }}
            aria-busy={loading}
          >
            {error ? (
              <p role="alert" className="text-sm text-destructive">
                {error}
              </p>
            ) : null}
            {!loading && !items.length && !error ? (
              <div className="grid place-items-center gap-2 py-12 text-center text-sm text-muted-foreground">
                <p>{query ? "No files match your search." : "The library is empty."}</p>
                <Button type="button" variant="outline" size="sm" onClick={() => setTab("upload")}>
                  Upload a file
                </Button>
              </div>
            ) : null}
            <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6" aria-label="Media files">
              {items.map((a) => {
                const isSel = selected?.id === a.id;
                return (
                  <li key={a.id}>
                    <button
                      type="button"
                      onClick={() => setSelected(a)}
                      onDoubleClick={() => (a.kind !== "IMAGE" || a.alt.it ? onChoose(a) : setSelected(a))}
                      aria-pressed={isSel}
                      aria-label={`${a.filename}${a.alt.it ? ` — ${a.alt.it}` : ""}`}
                      className={cn(
                        "group relative block w-full overflow-hidden rounded-lg border-2 text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                        isSel ? "border-primary" : "border-transparent hover:border-border",
                      )}
                    >
                      <MediaThumb asset={a} sizes="140px" />
                      <span className="block truncate px-1 py-0.5 text-[11px] text-muted-foreground">
                        {a.filename}
                      </span>
                      {isSel ? (
                        <span className="absolute top-1 right-1 rounded-full bg-primary p-0.5 text-primary-foreground">
                          <Check className="size-3.5" aria-hidden />
                        </span>
                      ) : null}
                      {a.kind === "IMAGE" && !a.alt.it ? (
                        <span className="absolute top-1 left-1 rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-900">
                          No alt
                        </span>
                      ) : null}
                    </button>
                  </li>
                );
              })}
            </ul>
            {loading || loadingMore ? (
              <p className="py-3 text-center text-sm text-muted-foreground">Loading…</p>
            ) : null}
            {!loading && !loadingMore && result && result.page < result.pageCount ? (
              <div className="py-3 text-center">
                <Button type="button" variant="outline" size="sm" onClick={() => void loadMore()}>
                  Load more
                </Button>
              </div>
            ) : null}
          </div>
        </TabsContent>
        <TabsContent value="upload">
          <MediaUploader
            accept={accept}
            onAllDone={(uploaded) => {
              if (!uploaded.length) return;
              setTab("library");
              setQ("");
              setQuery("");
              setReload((n) => n + 1);
              setSelected(uploaded[0] ?? null);
            }}
          />
        </TabsContent>
      </Tabs>

      {selected ? (
        <SelectionFooter
          key={selected.id}
          asset={selected}
          onCancel={onCancel}
          onChoose={(a) => {
            setResult((prev) =>
              prev ? { ...prev, items: prev.items.map((p) => (p.id === a.id ? a : p)) } : prev,
            );
            onChoose(a);
          }}
        />
      ) : (
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          <Button type="button" disabled>
            Select
          </Button>
        </DialogFooter>
      )}
    </>
  );
}

/** Footer with the selected asset; asks for missing alt text before choosing an image. */
function SelectionFooter({
  asset,
  onCancel,
  onChoose,
}: {
  asset: MediaAssetDTO;
  onCancel: () => void;
  onChoose: (asset: MediaAssetDTO) => void;
}) {
  const id = useId();
  const needsAlt = asset.kind === "IMAGE" && !asset.alt.it;
  const [altIt, setAltIt] = useState(asset.alt.it);
  const [altEn, setAltEn] = useState(asset.alt.en);
  const [pending, startTransition] = useTransition();

  const submit = () => {
    if (!needsAlt) return onChoose(asset);
    if (!altIt.trim()) return;
    startTransition(async () => {
      const res = await updateMediaAction(asset.id, {
        altIt,
        altEn,
        captionIt: asset.caption.it,
        captionEn: asset.caption.en,
        credit: asset.credit,
        focalX: asset.focalX,
        focalY: asset.focalY,
      });
      if (!res.ok) {
        toast.error(res.message);
        return;
      }
      onChoose(res.data);
    });
  };

  return (
    <div className="grid gap-3 border-t pt-3">
      <div className="flex items-center gap-3 text-sm">
        <MediaThumb asset={asset} className="size-12 shrink-0 rounded" sizes="48px" />
        <div className="min-w-0">
          <p className="truncate font-medium">{asset.filename}</p>
          <p className="text-xs text-muted-foreground">
            {asset.width && asset.height ? `${asset.width}×${asset.height} · ` : ""}
            {formatBytes(asset.size)}
          </p>
        </div>
      </div>
      {needsAlt ? (
        <div className="grid gap-2 sm:grid-cols-2">
          <div className="grid gap-1">
            <Label htmlFor={`${id}-alt-it`}>Alt text (Italian) *</Label>
            <Input
              id={`${id}-alt-it`}
              value={altIt}
              onChange={(e) => setAltIt(e.target.value)}
              placeholder="Describe the image for screen readers"
              maxLength={300}
            />
          </div>
          <div className="grid gap-1">
            <Label htmlFor={`${id}-alt-en`}>Alt text (English)</Label>
            <Input
              id={`${id}-alt-en`}
              value={altEn}
              onChange={(e) => setAltEn(e.target.value)}
              maxLength={300}
            />
          </div>
        </div>
      ) : null}
      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="button" onClick={submit} disabled={pending || (needsAlt && !altIt.trim())}>
          {pending ? "Saving…" : needsAlt ? "Save alt text & select" : "Select"}
        </Button>
      </DialogFooter>
    </div>
  );
}
