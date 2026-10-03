"use client";

import { useCallback, useId, useRef, useState } from "react";
import { CircleCheck, CircleAlert, CloudUpload, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { MediaAssetDTO } from "@/lib/content/media";
import { formatBytes } from "@/lib/content/media";
import { cn } from "@/lib/utils";

/**
 * Drag & drop / multi-file uploader with per-file progress. Each file is POSTed separately to
 * /api/media (XHR for upload progress). The server sniffs the real type and enforces limits;
 * the `accept` attribute here is only a convenience.
 */
export type UploadAccept = "IMAGE" | "DOCUMENT" | undefined;

const ACCEPT_ATTR: Record<"IMAGE" | "DOCUMENT" | "ANY", string> = {
  IMAGE: "image/jpeg,image/png,image/webp,image/avif,image/gif",
  DOCUMENT: "application/pdf",
  ANY: "image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf",
};

type UploadItem = {
  key: string;
  name: string;
  size: number;
  progress: number;
  status: "queued" | "uploading" | "done" | "error";
  error?: string;
};

const CONCURRENCY = 3;

function xsrfToken(): string {
  const match = document.cookie.split("; ").find((c) => c.startsWith("XSRF-TOKEN="));
  return match ? decodeURIComponent(match.slice("XSRF-TOKEN=".length)) : "";
}

function uploadOne(
  file: File,
  accept: UploadAccept,
  onProgress: (pct: number) => void,
): Promise<{ ok: true; asset: MediaAssetDTO } | { ok: false; message: string }> {
  return new Promise((resolve) => {
    const xhr = new XMLHttpRequest();
    const form = new FormData();
    form.append("file", file);
    if (accept) form.append("kind", accept);
    xhr.open("POST", "/api/media");
    xhr.responseType = "json";
    // Laravel CSRF (the reference relied on the Origin check only; the server checks both).
    xhr.setRequestHeader("X-XSRF-TOKEN", xsrfToken());
    xhr.setRequestHeader("Accept", "application/json");
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
    };
    xhr.onload = () => {
      const body = xhr.response as { ok?: boolean; asset?: MediaAssetDTO; message?: string } | null;
      if (xhr.status >= 200 && xhr.status < 300 && body?.ok && body.asset)
        resolve({ ok: true, asset: body.asset });
      else resolve({ ok: false, message: body?.message ?? `Upload failed (${xhr.status}).` });
    };
    xhr.onerror = () => resolve({ ok: false, message: "Network error. Check your connection and retry." });
    xhr.send(form);
  });
}

export function MediaUploader({
  accept,
  onUploaded,
  onAllDone,
  className,
}: {
  accept?: UploadAccept;
  onUploaded?: (asset: MediaAssetDTO) => void;
  /** Called once a batch finishes (e.g. router.refresh()). */
  onAllDone?: (uploaded: MediaAssetDTO[]) => void;
  className?: string;
}) {
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [items, setItems] = useState<UploadItem[]>([]);
  const [dragging, setDragging] = useState(false);
  const [announce, setAnnounce] = useState("");

  const update = (key: string, patch: Partial<UploadItem>) =>
    setItems((prev) => prev.map((i) => (i.key === key ? { ...i, ...patch } : i)));

  const start = useCallback(
    async (files: File[]) => {
      if (!files.length) return;
      const batch = files.map((f) => ({
        file: f,
        item: {
          key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
          name: f.name,
          size: f.size,
          progress: 0,
          status: "queued" as const,
        },
      }));
      setItems((prev) => [...batch.map((b) => b.item), ...prev].slice(0, 50));
      setAnnounce(`Uploading ${files.length} file${files.length > 1 ? "s" : ""}…`);

      const uploaded: MediaAssetDTO[] = [];
      let failed = 0;
      const queue = [...batch];
      const worker = async () => {
        while (queue.length) {
          const next = queue.shift()!;
          update(next.item.key, { status: "uploading" });
          const res = await uploadOne(next.file, accept, (progress) => update(next.item.key, { progress }));
          if (res.ok) {
            uploaded.push(res.asset);
            update(next.item.key, { status: "done", progress: 100 });
            onUploaded?.(res.asset);
          } else {
            failed++;
            update(next.item.key, { status: "error", error: res.message });
          }
        }
      };
      await Promise.all(Array.from({ length: Math.min(CONCURRENCY, batch.length) }, worker));
      setAnnounce(
        `${uploaded.length} file${uploaded.length === 1 ? "" : "s"} uploaded${failed ? `, ${failed} failed` : ""}.`,
      );
      onAllDone?.(uploaded);
    },
    [accept, onUploaded, onAllDone],
  );

  return (
    <div className={cn("grid gap-3", className)}>
      <div
        onDragOver={(e) => {
          e.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragging(false);
          void start(Array.from(e.dataTransfer.files));
        }}
        className={cn(
          "flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition-colors",
          dragging ? "border-primary bg-primary/5" : "border-border bg-muted/30",
        )}
        data-testid="media-dropzone"
      >
        <CloudUpload className="size-8 text-muted-foreground" aria-hidden />
        <p className="text-sm">
          <span className="font-medium">Drag &amp; drop files here</span> or{" "}
          <Button
            type="button"
            variant="link"
            className="h-auto p-0"
            onClick={() => inputRef.current?.click()}
          >
            choose files
          </Button>
        </p>
        <p className="text-xs text-muted-foreground">
          {accept === "DOCUMENT"
            ? "PDF up to 25 MB"
            : accept === "IMAGE"
              ? "JPG, PNG, WebP, AVIF or GIF up to 10 MB. Location data is removed automatically."
              : "Images (JPG, PNG, WebP, AVIF, GIF · 10 MB) and PDFs (25 MB)"}
        </p>
        <label htmlFor={inputId} className="sr-only">
          Upload files
        </label>
        <input
          ref={inputRef}
          id={inputId}
          type="file"
          multiple
          accept={ACCEPT_ATTR[accept ?? "ANY"]}
          className="sr-only"
          onChange={(e) => {
            void start(Array.from(e.target.files ?? []));
            e.target.value = "";
          }}
        />
      </div>

      <p className="sr-only" aria-live="polite">
        {announce}
      </p>

      {items.length ? (
        <ul className="grid gap-2" aria-label="Uploads">
          {items.map((i) => (
            <li
              key={i.key}
              className="flex items-center gap-3 rounded-lg border bg-background px-3 py-2 text-sm"
            >
              {i.status === "done" ? (
                <CircleCheck className="size-4 shrink-0 text-emerald-600" aria-label="Uploaded" />
              ) : i.status === "error" ? (
                <CircleAlert className="size-4 shrink-0 text-destructive" aria-label="Failed" />
              ) : (
                <span className="size-4 shrink-0 animate-pulse rounded-full bg-primary/40" aria-hidden />
              )}
              <div className="min-w-0 flex-1">
                <div className="flex justify-between gap-2">
                  <span className="truncate font-medium">{i.name}</span>
                  <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                    {i.status === "uploading" ? `${i.progress}%` : formatBytes(i.size)}
                  </span>
                </div>
                {i.status === "uploading" || i.status === "queued" ? (
                  <div
                    className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-label={`Uploading ${i.name}`}
                    aria-valuenow={i.progress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                  >
                    <div
                      className="h-full bg-primary transition-[width]"
                      style={{ width: `${i.progress}%` }}
                    />
                  </div>
                ) : null}
                {i.error ? <p className="mt-0.5 text-xs text-destructive">{i.error}</p> : null}
              </div>
              {i.status === "done" || i.status === "error" ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  aria-label={`Dismiss ${i.name}`}
                  onClick={() => setItems((prev) => prev.filter((p) => p.key !== i.key))}
                >
                  <X />
                </Button>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
