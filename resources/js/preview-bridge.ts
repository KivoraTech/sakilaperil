/**
 * Runs inside the page editor's live-preview iframe (`/preview/{locale}/pages/{id}?embed=1&draft=…`).
 * Port of the reference's preview-bridge.tsx (React) to the Blade preview:
 * - on `preview-refresh`, re-fetches this URL and swaps the draft blocks in place (#preview-content),
 *   so the scroll position is kept and the iframe never reloads;
 * - on `preview-focus`, scrolls to and briefly outlines a section (retried after the next render when
 *   the section is new and not on screen yet);
 * - blocks navigation and form submissions, so the preview never leaves this page and never sends
 *   real contact forms or donations;
 * - reports every render back to the editor (`stamp` changes when new content is shown).
 * Messages are accepted only from the parent window on the same origin.
 */

export type PreviewMessage =
  | { type: "sakila:preview-refresh" }
  | { type: "sakila:preview-focus"; sectionId: string }
  | { type: "sakila:preview-rendered"; stamp: string; staged: boolean; initial: boolean }
  | { type: "sakila:preview-blocked"; what: "link" | "form" };

const HIGHLIGHT_MS = 1600;
const ROOT_ID = "preview-content";

let highlightTimer: number | undefined;
let pendingFocus: string | null = null;
let reported = false;
let refreshing: Promise<void> | null = null;
let refreshAgain = false;

const root = () => document.getElementById(ROOT_ID);
const tell = (msg: PreviewMessage) => window.parent.postMessage(msg, window.location.origin);

/** Scroll to a section and outline it briefly. Returns false when it is not rendered (yet). */
function focusSection(sectionId: string): boolean {
  const marker = document.querySelector<HTMLElement>(`[data-section-id="${CSS.escape(sectionId)}"]`);
  // The marker is `display: contents` (no box), so scroll to its first rendered child.
  const target = (marker?.firstElementChild as HTMLElement | null) ?? marker;
  if (!target) return false;
  document
    .querySelectorAll("[data-preview-highlight]")
    .forEach((el) => el.removeAttribute("data-preview-highlight"));
  // Set first: the attribute also adds scroll-margin so the sticky site header doesn't cover it.
  target.setAttribute("data-preview-highlight", "");
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  target.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
  window.clearTimeout(highlightTimer);
  highlightTimer = window.setTimeout(() => target.removeAttribute("data-preview-highlight"), HIGHLIGHT_MS);
  return true;
}

function reportRendered() {
  const el = root();
  if (!el) return;
  if (pendingFocus && focusSection(pendingFocus)) pendingFocus = null;
  // `initial` lets the editor re-send a refresh it posted before this listener existed.
  tell({
    type: "sakila:preview-rendered",
    stamp: el.dataset.previewStamp ?? "",
    staged: el.dataset.previewStaged === "1",
    initial: !reported,
  });
  reported = true;
}

/** Fetch the current state of this preview and swap the blocks in place. */
async function refresh(): Promise<void> {
  const res = await fetch(window.location.href, {
    credentials: "same-origin",
    headers: { Accept: "text/html" },
    cache: "no-store",
  });
  if (!res.ok) throw new Error(`Preview refresh failed (${res.status})`);
  const doc = new DOMParser().parseFromString(await res.text(), "text/html");
  const next = doc.getElementById(ROOT_ID);
  const current = root();
  if (!next || !current) throw new Error("Preview markup missing");
  current.replaceChildren(...Array.from(next.childNodes).map((n) => document.importNode(n, true)));
  current.dataset.previewStamp = next.dataset.previewStamp ?? "";
  current.dataset.previewStaged = next.dataset.previewStaged ?? "0";
  // Swapped content skips the scroll-reveal entrance (it is an editing preview): show it at once.
  current.querySelectorAll("[data-reveal]").forEach((el) => el.classList.add("is-visible"));
  // Public islands that care (counters, galleries…) can re-initialise on this event.
  document.dispatchEvent(new CustomEvent("sakila:content-swapped", { detail: { root: current } }));
}

function requestRefresh() {
  if (refreshing) {
    refreshAgain = true;
    return;
  }
  refreshing = refresh()
    .catch(() => {
      // Fall back to a full reload (scroll restored below) rather than showing stale content.
      sessionStorage.setItem("sakila:preview-scroll", String(window.scrollY));
      window.location.reload();
    })
    .finally(() => {
      refreshing = null;
      reportRendered();
      if (refreshAgain) {
        refreshAgain = false;
        requestRefresh();
      }
    });
}

function init() {
  const el = root();
  if (!el || el.dataset.previewEmbed === undefined || window.parent === window) return;

  const style = document.createElement("style");
  style.textContent =
    "[data-preview-highlight]{outline:3px solid #e8a33d;outline-offset:-3px;scroll-margin-top:5rem}";
  document.head.appendChild(style);

  const savedScroll = sessionStorage.getItem("sakila:preview-scroll");
  if (savedScroll !== null) {
    sessionStorage.removeItem("sakila:preview-scroll");
    window.scrollTo(0, Number(savedScroll) || 0);
  }

  window.addEventListener("message", (event: MessageEvent) => {
    if (event.origin !== window.location.origin || event.source !== window.parent) return;
    const data = event.data as PreviewMessage | undefined;
    if (!data || typeof data !== "object") return;
    if (data.type === "sakila:preview-refresh") requestRefresh();
    else if (data.type === "sakila:preview-focus" && typeof data.sectionId === "string") {
      pendingFocus = focusSection(data.sectionId) ? null : data.sectionId;
    }
  });
  document.addEventListener(
    "click",
    (event) => {
      const link = (event.target as Element | null)?.closest?.("a[href]");
      if (!link) return;
      if ((link.getAttribute("href") ?? "").startsWith("#")) return; // in-page anchors (e.g. #dona) are fine
      event.preventDefault();
      tell({ type: "sakila:preview-blocked", what: "link" });
    },
    true,
  );
  document.addEventListener(
    "submit",
    (event) => {
      event.preventDefault();
      tell({ type: "sakila:preview-blocked", what: "form" });
    },
    true,
  );
  reportRendered();
}

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
else init();
