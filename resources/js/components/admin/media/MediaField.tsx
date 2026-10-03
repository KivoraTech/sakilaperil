"use client";

import { useEffect, useState } from "react";
import { ImagePlus, FilePlus2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { getMediaAssetAction } from "@/lib/actions/media";
import type { MediaAssetDTO } from "@/lib/content/media";
import { formatBytes } from "@/lib/content/media";
import { cn } from "@/lib/utils";
import { MediaPicker } from "./MediaPicker";
import { MediaThumb } from "./MediaThumb";

/**
 * Form control for a single media reference (stores the asset id). Shows a preview and
 * opens the MediaPicker. Usable with React Hook Form via a Controller / FormField:
 *
 *   <MediaField value={field.value} onChange={field.onChange} accept="IMAGE" initialAsset={asset} />
 */
export function MediaField({
  value,
  onChange,
  accept = "IMAGE",
  initialAsset,
  id,
  invalid,
  className,
}: {
  value: string | null | undefined;
  onChange: (id: string | null, asset: MediaAssetDTO | null) => void;
  accept?: "IMAGE" | "DOCUMENT";
  initialAsset?: MediaAssetDTO | null;
  id?: string;
  invalid?: boolean;
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const [asset, setAsset] = useState<MediaAssetDTO | null>(initialAsset ?? null);

  // Load the preview when the value was set elsewhere (e.g. form reset / "use as cover").
  useEffect(() => {
    if (!value || asset?.id === value) return;
    let cancelled = false;
    void getMediaAssetAction(value).then((res) => {
      if (!cancelled) setAsset(res.ok ? res.data : null);
    });
    return () => {
      cancelled = true;
    };
  }, [value, asset?.id]);

  const shown = value && asset?.id === value ? asset : null;
  const Icon = accept === "DOCUMENT" ? FilePlus2 : ImagePlus;

  return (
    <div className={cn("grid min-w-0 grid-cols-[minmax(0,1fr)] gap-2", className)}>
      {shown ? (
        <div
          className={cn(
            "flex items-center gap-3 rounded-lg border bg-background p-2",
            invalid && "border-destructive",
          )}
        >
          <MediaThumb asset={shown} className="size-16 shrink-0 rounded-md" sizes="64px" />
          <div className="min-w-0 flex-1 text-sm">
            <p className="truncate font-medium">{shown.filename}</p>
            <p className="text-xs text-muted-foreground">
              {shown.width && shown.height ? `${shown.width}×${shown.height} · ` : ""}
              {formatBytes(shown.size)}
            </p>
            {shown.kind === "IMAGE" && !shown.alt.it ? (
              <p className="text-xs text-amber-700">Missing alt text — required before publishing.</p>
            ) : null}
          </div>
          <div className="flex shrink-0 gap-1">
            <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)} id={id}>
              Replace
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label="Remove"
              onClick={() => {
                setAsset(null);
                onChange(null, null);
              }}
            >
              <X />
            </Button>
          </div>
        </div>
      ) : (
        <Button
          type="button"
          variant="outline"
          id={id}
          onClick={() => setOpen(true)}
          aria-invalid={invalid}
          className="h-auto justify-start gap-2 border-dashed py-3"
        >
          <Icon aria-hidden />
          {accept === "DOCUMENT" ? "Choose a PDF…" : "Choose an image…"}
        </Button>
      )}
      <MediaPicker
        open={open}
        onOpenChange={setOpen}
        accept={accept}
        onSelect={(a) => {
          setAsset(a);
          onChange(a.id, a);
        }}
      />
    </div>
  );
}
