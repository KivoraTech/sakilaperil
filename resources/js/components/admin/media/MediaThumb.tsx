import Image from "next/image";
import { FileText } from "lucide-react";
import type { MediaAssetDTO } from "@/lib/content/media";
import { cn } from "@/lib/utils";

/** Square thumbnail for an asset (next/image for images, an icon card for PDFs). */
export function MediaThumb({
  asset,
  sizes = "160px",
  className,
}: {
  asset: Pick<MediaAssetDTO, "url" | "kind" | "alt" | "filename" | "mimeType">;
  sizes?: string;
  className?: string;
}) {
  if (asset.kind === "IMAGE") {
    return (
      <div className={cn("relative aspect-square overflow-hidden bg-muted", className)}>
        <Image
          src={asset.url}
          alt={asset.alt.it || ""}
          fill
          sizes={sizes}
          className="object-cover"
          unoptimized={asset.mimeType === "image/gif"}
        />
      </div>
    );
  }
  return (
    <div
      className={cn(
        "flex aspect-square flex-col items-center justify-center gap-1 bg-muted p-2 text-muted-foreground",
        className,
      )}
    >
      <FileText className="size-8" aria-hidden />
      <span className="text-[10px] font-semibold tracking-wider uppercase">PDF</span>
    </div>
  );
}
