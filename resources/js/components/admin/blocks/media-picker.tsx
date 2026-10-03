"use client";

import { useEffect, useId, useState, useSyncExternalStore, useTransition } from "react";
import { ImageIcon, Search } from "lucide-react";
import { toast } from "sonner";
import { MediaUploader } from "@/components/admin/media/MediaUploader";
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
import { getMediaAssetAction, searchMediaAction, updateMediaAction } from "@/lib/actions/media";
import type { MediaAssetDTO } from "@/lib/content/media";
import type { PickerAsset } from "@/lib/queries/admin/pages";
import { cn } from "@/lib/utils";
import { useReferences } from "./references-context";

const PAGE_SIZE = 24;

const toPickerAsset = (a: MediaAssetDTO): PickerAsset => ({
  id: a.id,
  url: a.url,
  filename: a.filename,
  alt: a.alt.it,
  width: a.width,
  height: a.height,
});

/**
 * Assets picked or looked up in this browser session, so a chooser can show the thumbnail of an
 * image that is not in the preloaded references (older images, fresh uploads).
 */
const assetCache = new Map<string, PickerAsset>();
const cacheListeners = new Set<() => void>();
function cacheAsset(a: PickerAsset) {
  assetCache.set(a.id, a);
  cacheListeners.forEach((l) => l());
}
function subscribeCache(l: () => void) {
  cacheListeners.add(l);
  return () => cacheListeners.delete(l);
}

/**
 * Image picker for the page builder: searchable, paged media library plus an Upload tab, so
 * editors never have to leave the page (and lose unsaved changes) to add a photo. Images must
 * have an Italian description before they can be used (publishing requires it).
 */
export function MediaPickerDialog({
  open,
  onOpenChange,
  value,
  onSelect,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  value?: string;
  onSelect: (asset: PickerAsset | { id: string }) => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[90vh] flex-col gap-3 overflow-hidden sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Choose an image</DialogTitle>
          <DialogDescription>
            Pick a photo from your library, or upload a new one from your computer or phone.
          </DialogDescription>
        </DialogHeader>
        {/* Mounted only while open: search and tab state reset on every open. */}
        {open ? (
          <PickerBody
            value={value}
            onChoose={(a) => {
              if ("url" in a) cacheAsset(a);
              onSelect(a);
              onOpenChange(false);
            }}
          />
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

type Result = { key: string; items: MediaAssetDTO[]; page: number; pageCount: number; error: string | null };

function PickerBody({
  value,
  onChoose,
}: {
  value?: string;
  onChoose: (asset: PickerAsset | { id: string }) => void;
}) {
  const [tab, setTab] = useState<"library" | "upload">("library");
  const [q, setQ] = useState("");
  const [query, setQuery] = useState("");
  const [reload, setReload] = useState(0);
  const [result, setResult] = useState<Result | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  const [needsAlt, setNeedsAlt] = useState<MediaAssetDTO | null>(null);
  const [manualId, setManualId] = useState("");
  const requestKey = `${query}|${reload}`;
  const loading = result?.key !== requestKey;

  useEffect(() => {
    const t = setTimeout(() => setQuery(q), 250);
    return () => clearTimeout(t);
  }, [q]);

  useEffect(() => {
    let cancelled = false;
    void searchMediaAction({ q: query, kind: "IMAGE", page: 1, pageSize: PAGE_SIZE }).then((res) => {
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
  }, [query, requestKey]);

  const loadMore = async () => {
    if (!result) return;
    setLoadingMore(true);
    const res = await searchMediaAction({
      q: query,
      kind: "IMAGE",
      page: result.page + 1,
      pageSize: PAGE_SIZE,
    });
    setLoadingMore(false);
    if (!res.ok) return toast.error(res.message);
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
  };

  const pick = (a: MediaAssetDTO) => (a.alt.it ? onChoose(toPickerAsset(a)) : setNeedsAlt(a));

  if (needsAlt) {
    return (
      <AltTextStep
        asset={needsAlt}
        onBack={() => setNeedsAlt(null)}
        onDone={(a) => onChoose(toPickerAsset(a))}
      />
    );
  }

  const items = result?.items ?? [];
  return (
    <>
      <Tabs value={tab} onValueChange={(v) => setTab(v as "library" | "upload")} className="min-h-0 flex-1">
        <TabsList>
          <TabsTrigger value="library">Your photos</TabsTrigger>
          <TabsTrigger value="upload">Upload new</TabsTrigger>
        </TabsList>
        <TabsContent value="library" className="flex min-h-0 flex-col gap-3">
          <div className="relative">
            <Search
              className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
              aria-hidden
            />
            <Input
              type="search"
              aria-label="Search images"
              placeholder="Search by file name or description"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              className="pl-8"
            />
          </div>
          <div className="-mx-1 max-h-[50vh] min-h-[200px] overflow-y-auto px-1" aria-busy={loading}>
            {result?.error ? (
              <p role="alert" className="text-sm text-destructive">
                {result.error}
              </p>
            ) : null}
            {!loading && !items.length && !result?.error ? (
              <div className="grid place-items-center gap-2 py-10 text-center text-sm text-muted-foreground">
                <p>{query ? "No images match your search." : "You have no photos yet."}</p>
                <Button type="button" variant="outline" size="sm" onClick={() => setTab("upload")}>
                  Upload a photo
                </Button>
              </div>
            ) : null}
            {items.length ? (
              <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4" role="listbox" aria-label="Images">
                {items.map((img) => {
                  const selected = img.id === value;
                  return (
                    <li key={img.id} role="option" aria-selected={selected}>
                      <button
                        type="button"
                        onClick={() => pick(img)}
                        className={cn(
                          "group relative w-full overflow-hidden rounded-lg border text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                          selected && "ring-2 ring-primary",
                        )}
                      >
                        <span className="block aspect-[4/3] bg-muted">
                          {/* admin thumbnails from any storage origin */}
                          <img src={img.url} alt="" loading="lazy" className="size-full object-cover" />
                        </span>
                        <span className="block truncate px-2 py-1.5 text-xs" title={img.filename}>
                          {img.alt.it || img.filename}
                        </span>
                        {!img.alt.it ? (
                          <span className="absolute top-1 left-1 rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-900">
                            Needs description
                          </span>
                        ) : null}
                      </button>
                    </li>
                  );
                })}
              </ul>
            ) : null}
            {loading || loadingMore ? (
              <p className="py-3 text-center text-sm text-muted-foreground">Loading…</p>
            ) : null}
            {!loading && !loadingMore && result && result.page < result.pageCount ? (
              <div className="py-3 text-center">
                <Button type="button" variant="outline" size="sm" onClick={() => void loadMore()}>
                  Show more photos
                </Button>
              </div>
            ) : null}
          </div>
        </TabsContent>
        <TabsContent value="upload">
          <MediaUploader
            accept="IMAGE"
            onAllDone={(uploaded) => {
              const first = uploaded[0];
              if (!first) return;
              // One photo: use it straight away (asking for a description first if needed).
              if (uploaded.length === 1) return pick(first);
              setTab("library");
              setQ("");
              setQuery("");
              setReload((n) => n + 1);
            }}
          />
        </TabsContent>
      </Tabs>
      <details className="rounded-md border px-3 py-2 text-sm">
        <summary className="cursor-pointer text-muted-foreground">Advanced: use an image ID</summary>
        <div className="mt-2 flex items-end gap-2">
          <div className="grid flex-1 gap-1.5">
            <Label htmlFor="manual-asset-id" className="text-xs text-muted-foreground">
              Image ID (from Admin → Media)
            </Label>
            <Input
              id="manual-asset-id"
              value={manualId}
              onChange={(e) => setManualId(e.target.value.trim())}
            />
          </div>
          <Button
            type="button"
            variant="outline"
            disabled={!manualId}
            onClick={() => onChoose({ id: manualId })}
          >
            Use ID
          </Button>
        </div>
      </details>
    </>
  );
}

/** A photo needs an Italian description (alt text) before it can be used on the site. */
function AltTextStep({
  asset,
  onBack,
  onDone,
}: {
  asset: MediaAssetDTO;
  onBack: () => void;
  onDone: (asset: MediaAssetDTO) => void;
}) {
  const id = useId();
  const [altIt, setAltIt] = useState(asset.alt.it);
  const [altEn, setAltEn] = useState(asset.alt.en);
  const [pending, start] = useTransition();
  const save = () =>
    start(async () => {
      const res = await updateMediaAction(asset.id, {
        altIt,
        altEn,
        captionIt: asset.caption.it,
        captionEn: asset.caption.en,
        credit: asset.credit,
        focalX: asset.focalX,
        focalY: asset.focalY,
      });
      if (!res.ok) return void toast.error(res.message);
      onDone(res.data);
    });
  return (
    <form
      className="grid gap-4"
      onSubmit={(e) => {
        e.preventDefault();
        if (altIt.trim()) save();
      }}
    >
      <div className="flex items-center gap-3">
        {/* admin thumbnail */}
        <img src={asset.url} alt="" className="size-20 shrink-0 rounded-md object-cover" />
        <p className="text-sm text-muted-foreground">
          Describe this photo in a few words. Blind visitors hear this description, and Google uses it too.
        </p>
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="grid gap-1.5">
          <Label htmlFor={`${id}-it`}>Description (Italian) *</Label>
          <Input
            id={`${id}-it`}
            value={altIt}
            onChange={(e) => setAltIt(e.target.value)}
            placeholder="Es. Bambini che leggono in una classe a Kathmandu"
            maxLength={300}
            required
            autoFocus
          />
        </div>
        <div className="grid gap-1.5">
          <Label htmlFor={`${id}-en`}>Description (English)</Label>
          <Input id={`${id}-en`} value={altEn} onChange={(e) => setAltEn(e.target.value)} maxLength={300} />
        </div>
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" onClick={onBack}>
          Back
        </Button>
        <Button type="submit" disabled={pending || !altIt.trim()}>
          {pending ? "Saving…" : "Save and use this photo"}
        </Button>
      </DialogFooter>
    </form>
  );
}

/** Finds an asset in the preloaded references or the session cache, fetching it once if needed. */
function useAsset(assetId: string | undefined): PickerAsset | undefined {
  const { images } = useReferences();
  const cached = useSyncExternalStore(
    subscribeCache,
    () => (assetId ? assetCache.get(assetId) : undefined),
    () => undefined,
  );
  const fromRefs = assetId ? images.find((i) => i.id === assetId) : undefined;
  const found = fromRefs ?? cached;
  useEffect(() => {
    if (!assetId || found) return;
    let cancelled = false;
    void getMediaAssetAction(assetId).then((res) => {
      if (!cancelled && res.ok) cacheAsset(toPickerAsset(res.data));
    });
    return () => {
      cancelled = true;
    };
  }, [assetId, found]);
  return found;
}

/** Thumbnail + choose/remove buttons for one image reference. */
export function ImageChooser({
  assetId,
  onChange,
  optional,
  invalid,
  describedBy,
  id,
  label = "image",
}: {
  /** Used in the button's accessible name: "Choose <label>" / "Change <label>". */
  label?: string;
  assetId: string | undefined;
  onChange: (assetId: string | undefined) => void;
  optional?: boolean;
  invalid?: boolean;
  describedBy?: string;
  id?: string;
}) {
  const [open, setOpen] = useState(false);
  const asset = useAsset(assetId);
  return (
    <div className="flex items-center gap-3">
      <div
        className={cn(
          "flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted",
          invalid && "border-destructive",
        )}
      >
        {asset ? (
          // admin thumbnail
          <img src={asset.url} alt="" className="size-full object-cover" />
        ) : (
          <ImageIcon className="size-6 text-muted-foreground" aria-hidden />
        )}
      </div>
      <div className="grid min-w-0 gap-1">
        <p className="truncate text-xs text-muted-foreground">
          {asset ? asset.alt || asset.filename : assetId ? "Loading image…" : "No image selected"}
        </p>
        <div className="flex gap-2">
          <Button
            id={id}
            type="button"
            size="sm"
            variant="outline"
            onClick={() => setOpen(true)}
            aria-invalid={invalid || undefined}
            aria-describedby={describedBy}
            aria-label={`${assetId ? "Change" : "Choose"} ${label.toLowerCase()}`}
          >
            {assetId ? "Change image" : "Choose image"}
          </Button>
          {assetId && optional ? (
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() => onChange(undefined)}
              aria-label={`Remove ${label.toLowerCase()}`}
            >
              Remove
            </Button>
          ) : null}
        </div>
      </div>
      <MediaPickerDialog
        open={open}
        onOpenChange={setOpen}
        value={assetId}
        onSelect={(a) => onChange(a.id)}
      />
    </div>
  );
}
