"use client";

import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from "react";
import { stagePreviewDraftAction } from "@/lib/actions/pages";
import { isBlockType } from "@/lib/blocks/registry";
import type { Locale } from "@/lib/i18n/config";
import type { BuilderSection } from "./section-list";

/** Debounce between the last edit and staging it for the preview. */
export const PREVIEW_DEBOUNCE_MS = 500;

// ── media query + remembered preference (SSR-safe: false / null on the server) ──

function subscribeMedia(query: string) {
  return (onChange: () => void) => {
    const mql = window.matchMedia(query);
    mql.addEventListener("change", onChange);
    return () => mql.removeEventListener("change", onChange);
  };
}

export function useMediaQuery(query: string) {
  return useSyncExternalStore(
    subscribeMedia(query),
    () => window.matchMedia(query).matches,
    () => false,
  );
}

const PREF_KEY = "sakila:builder:live-preview";
const prefListeners = new Set<() => void>();

function readPref(): "on" | "off" | null {
  try {
    const v = window.localStorage.getItem(PREF_KEY);
    return v === "on" || v === "off" ? v : null;
  } catch {
    return null;
  }
}

/** The editor's "Live preview" switch: remembered per browser, defaulting to on for wide screens. */
export function useLivePreviewPreference(): [boolean, (on: boolean) => void] {
  const wide = useMediaQuery("(min-width: 1280px)");
  const pref = useSyncExternalStore(
    (cb) => {
      prefListeners.add(cb);
      return () => prefListeners.delete(cb);
    },
    readPref,
    () => null,
  );
  const set = useCallback((on: boolean) => {
    try {
      window.localStorage.setItem(PREF_KEY, on ? "on" : "off");
    } catch {
      /* private mode: the choice just isn't remembered */
    }
    prefListeners.forEach((l) => l());
  }, []);
  return [pref ? pref === "on" : wide, set];
}

// ── staging ──

function newClientId() {
  return typeof crypto !== "undefined" && "randomUUID" in crypto
    ? crypto.randomUUID()
    : `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 12)}`;
}

/**
 * One id per browser tab + page + language. It survives the builder remounting after a save
 * (sessionStorage), so the preview iframe keeps its URL and scroll position.
 */
function useClientId(pageId: string, locale: Locale) {
  const [id] = useState(() => {
    if (typeof window === "undefined") return "";
    const key = `sakila:preview-client:${pageId}:${locale}`;
    try {
      const existing = window.sessionStorage.getItem(key);
      if (existing && /^[A-Za-z0-9_-]{8,64}$/.test(existing)) return existing;
      const created = newClientId();
      window.sessionStorage.setItem(key, created);
      return created;
    } catch {
      return newClientId();
    }
  });
  return id;
}

export type LivePreviewStatus = "idle" | "staging" | "error";

/**
 * Keeps a server-side copy of the editor's unsaved state for the preview iframe: stages it
 * (debounced) whenever `sections`/`title` change while `enabled`, never sends two requests at
 * once, and skips identical payloads. `refreshToken` increments after each successful stage.
 */
export function useLivePreview({
  pageId,
  locale,
  enabled,
  title,
  sections,
}: {
  pageId: string;
  locale: Locale;
  enabled: boolean;
  title: string;
  sections: BuilderSection[];
}) {
  const clientId = useClientId(pageId, locale);
  const [status, setStatus] = useState<LivePreviewStatus>("idle");
  const [error, setError] = useState<string | null>(null);
  const [stagedAt, setStagedAt] = useState<string | null>(null);
  const [refreshToken, setRefreshToken] = useState(0);

  const payload = JSON.stringify({
    title,
    // Unknown/broken sections can't render (and would fail validation): leave them out.
    sections: sections
      .filter((s) => !s.broken && isBlockType(s.type))
      .map(({ key, type, isVisible, content, settings }) => ({
        key,
        type,
        isVisible,
        content,
        settings,
      })),
  });

  const latest = useRef(payload);
  const sent = useRef<string | null>(null);
  const inFlight = useRef<Promise<boolean> | null>(null);
  useEffect(() => {
    latest.current = payload;
  });

  /** Stage the latest state now (waits for a request in flight first). Resolves true on success. */
  const flush = useCallback(async (): Promise<boolean> => {
    while (inFlight.current) await inFlight.current;
    const body = latest.current;
    if (body === sent.current) return true;
    const run = (async () => {
      setStatus("staging");
      const r = await stagePreviewDraftAction({ pageId, locale, clientId, ...JSON.parse(body) });
      if (!r.ok) {
        setStatus("error");
        setError(r.message);
        return false;
      }
      sent.current = body;
      setError(null);
      setStagedAt(r.data.stagedAt);
      setRefreshToken((n) => n + 1);
      setStatus(latest.current === body ? "idle" : "staging");
      return true;
    })().catch(() => {
      setStatus("error");
      setError("The preview could not be updated. Check your connection.");
      return false;
    });
    inFlight.current = run;
    try {
      return await run;
    } finally {
      inFlight.current = null;
    }
  }, [pageId, locale, clientId]);

  useEffect(() => {
    if (!enabled || !clientId || payload === sent.current) return;
    // First stage is immediate so the iframe matches the editor right away.
    const delay = sent.current === null ? 0 : PREVIEW_DEBOUNCE_MS;
    const t = window.setTimeout(() => {
      void flush().then((okay) => {
        // Changes that arrived while the request was in flight are sent right after it.
        if (okay && latest.current !== sent.current) void flush();
      });
    }, delay);
    return () => window.clearTimeout(t);
  }, [enabled, clientId, payload, flush]);

  return { clientId, status, error, stagedAt, refreshToken, flush, retry: flush };
}
