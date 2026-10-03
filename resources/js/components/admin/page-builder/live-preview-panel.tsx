"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AlertTriangle, ExternalLink, Loader2, Monitor, RefreshCw, Smartphone, Tablet } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { LivePreviewStatus } from "./use-live-preview";

const DEVICES = {
  desktop: { label: "Desktop", width: 1280, icon: Monitor },
  tablet: { label: "Tablet", width: 768, icon: Tablet },
  phone: { label: "Phone", width: 390, icon: Smartphone },
} as const;
type Device = keyof typeof DEVICES;

/** Wait this long for the iframe to confirm a refresh before offering a reload. */
const STALE_AFTER_MS = 10_000;

type PreviewEvent =
  | { type: "sakila:preview-rendered"; stamp: string; staged: boolean; initial: boolean }
  | { type: "sakila:preview-blocked"; what: "link" | "form" };

/**
 * Side-by-side preview: the real public page (server-rendered from the staged unsaved draft)
 * in a same-origin iframe, rendered at a real device width and scaled to fit the panel, so the
 * site's own breakpoints apply. Talks to the iframe with postMessage (origin-checked).
 */
export function LivePreviewPanel({
  src,
  newTabHref,
  status,
  error,
  stagedAt,
  refreshToken,
  focusRequest,
  dirty,
  onRetry,
  onOpenNewTab,
  className,
}: {
  /** The editor has unsaved changes (the preview then shows more than what is saved). */
  dirty: boolean;
  /** Embedded preview URL (…?embed=1&draft=…). */
  src: string;
  newTabHref: string;
  status: LivePreviewStatus;
  error: string | null;
  stagedAt: string | null;
  refreshToken: number;
  /** Scroll the preview to this section (`n` changes on every request). */
  focusRequest: { sectionKey: string; n: number } | null;
  onRetry: () => void;
  onOpenNewTab: () => void;
  className?: string;
}) {
  const [device, setDevice] = useState<Device>("desktop");
  const [box, setBox] = useState({ width: 0, height: 0 });
  const [loaded, setLoaded] = useState(false);
  const [renderedStamp, setRenderedStamp] = useState<string | null>(null);
  const [stale, setStale] = useState(false);
  const [frameKey, setFrameKey] = useState(0);
  const viewport = useRef<HTMLDivElement>(null);
  const frame = useRef<HTMLIFrameElement>(null);

  const stagedRef = useRef(stagedAt);
  useEffect(() => {
    stagedRef.current = stagedAt;
  });

  const post = useCallback((message: unknown) => {
    frame.current?.contentWindow?.postMessage(message, window.location.origin);
  }, []);

  // Fit the device width into the panel.
  useEffect(() => {
    const el = viewport.current;
    if (!el) return;
    const ro = new ResizeObserver(([entry]) => {
      if (entry) setBox({ width: entry.contentRect.width, height: entry.contentRect.height });
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  // Messages from the iframe.
  useEffect(() => {
    const onMessage = (event: MessageEvent) => {
      if (event.origin !== window.location.origin || event.source !== frame.current?.contentWindow) return;
      const data = event.data as PreviewEvent | undefined;
      if (data?.type === "sakila:preview-rendered") {
        setLoaded(true);
        setRenderedStamp(data.stamp);
        // The iframe (re)loaded after we staged newer content: fetch it now.
        if (data.initial && stagedRef.current && data.stamp !== stagedRef.current) {
          post({ type: "sakila:preview-refresh" });
        }
      } else if (data?.type === "sakila:preview-blocked") {
        toast.message(
          data.what === "form"
            ? "Forms don’t send anything in the preview"
            : "Links are disabled in the live preview",
          { id: "preview-blocked", description: "Use “Open in new tab” to try the page in full." },
        );
      }
    };
    window.addEventListener("message", onMessage);
    return () => window.removeEventListener("message", onMessage);
  }, [post]);

  // New staged content → ask the iframe to re-render its server components.
  useEffect(() => {
    if (refreshToken > 0) post({ type: "sakila:preview-refresh" });
  }, [refreshToken, post]);

  useEffect(() => {
    if (focusRequest) post({ type: "sakila:preview-focus", sectionId: focusRequest.sectionKey });
  }, [focusRequest, post]);

  const waiting = !!stagedAt && renderedStamp !== stagedAt;
  useEffect(() => {
    if (!waiting) return;
    const t = window.setTimeout(() => setStale(true), STALE_AFTER_MS);
    return () => {
      window.clearTimeout(t);
      setStale(false);
    };
  }, [waiting, stagedAt]);

  const reload = () => {
    setLoaded(false);
    setStale(false);
    setFrameKey((k) => k + 1);
  };

  const { width: deviceWidth } = DEVICES[device];
  const scale = box.width > 0 ? Math.min(1, box.width / deviceWidth) : 1;
  const frameHeight = box.height > 0 ? box.height / scale : 800;
  const updating = status === "staging" || (loaded && waiting && !stale);

  return (
    <section
      aria-label="Live preview"
      className={cn("flex min-h-0 flex-col overflow-hidden rounded-xl border bg-muted/40", className)}
    >
      <div className="flex flex-wrap items-center gap-2 border-b bg-background px-3 py-2">
        <div role="group" aria-label="Preview size" className="flex rounded-lg border p-0.5">
          {(Object.keys(DEVICES) as Device[]).map((d) => {
            const { label, icon: Icon } = DEVICES[d];
            return (
              <Button
                key={d}
                type="button"
                size="icon-sm"
                variant={device === d ? "secondary" : "ghost"}
                aria-pressed={device === d}
                aria-label={`${label} width`}
                title={`${label} (${DEVICES[d].width}px)`}
                onClick={() => setDevice(d)}
              >
                <Icon aria-hidden />
              </Button>
            );
          })}
        </div>
        <span className="text-xs text-muted-foreground tabular-nums" aria-label="Zoom">
          {Math.round(scale * 100)}%
        </span>
        <span className="min-w-0 flex-1 truncate text-xs" role="status" aria-live="polite">
          {status === "error" ? (
            <span className="inline-flex items-center gap-1 text-destructive">
              <AlertTriangle className="size-3.5" aria-hidden /> {error ?? "Preview not updated"}
            </span>
          ) : stale ? (
            <span className="text-amber-800">Preview is taking longer than usual…</span>
          ) : updating ? (
            <span className="inline-flex items-center gap-1 text-muted-foreground">
              <Loader2 className="size-3.5 animate-spin" aria-hidden /> Updating preview…
            </span>
          ) : loaded ? (
            <span className="text-muted-foreground">
              {dirty ? "Preview up to date · not saved yet" : "Preview up to date"}
            </span>
          ) : null}
        </span>
        {status === "error" ? (
          <Button type="button" size="sm" variant="outline" onClick={onRetry}>
            Retry
          </Button>
        ) : stale ? (
          <Button type="button" size="sm" variant="outline" onClick={reload}>
            <RefreshCw aria-hidden /> Reload
          </Button>
        ) : null}
        <Button type="button" size="sm" variant="ghost" onClick={onOpenNewTab} title={newTabHref}>
          <ExternalLink aria-hidden /> Open in new tab
        </Button>
      </div>
      <div ref={viewport} className="relative min-h-0 flex-1 overflow-hidden">
        {box.width > 0 ? (
          <div
            className={cn(
              "mx-auto h-full",
              scale === 1 && device !== "desktop" && "border-x bg-background shadow-sm",
            )}
            style={{ width: deviceWidth * scale }}
          >
            <iframe
              key={frameKey}
              ref={frame}
              src={src}
              title="Live preview of this page"
              data-testid="live-preview-frame"
              onLoad={() => setLoaded(true)}
              className="origin-top-left border-0 bg-white"
              style={{ width: deviceWidth, height: frameHeight, transform: `scale(${scale})` }}
            />
          </div>
        ) : null}
        {!loaded ? (
          <div aria-hidden className="absolute inset-0 grid content-start gap-4 bg-background p-6">
            <div className="h-10 animate-pulse rounded-md bg-muted" />
            <div className="h-56 animate-pulse rounded-lg bg-muted" />
            <div className="h-6 w-2/3 animate-pulse rounded bg-muted" />
            <div className="h-6 w-1/2 animate-pulse rounded bg-muted" />
          </div>
        ) : null}
      </div>
    </section>
  );
}
