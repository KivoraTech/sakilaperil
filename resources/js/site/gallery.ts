/**
 * Gallery lightbox (port of components/site/gallery/gallery-grid.tsx): native <dialog> (focus trap,
 * Escape, inert page), close button first, prev/next buttons + arrow keys + swipe, "3 / 24" counter,
 * focus returns to the tile, `?foto=N` kept in the URL for sharing (album pages). Videos are
 * click-to-load facades (no third-party request until the visitor asks for it).
 * Data (items, labels, srcsets) comes from the server in <script type="application/json" data-gallery-data>.
 */
import { once } from "./once";

type Photo = { id: string; type: "PHOTO"; alt: string; caption: string; src: string; srcset: string | null };
type Video = {
  id: string;
  type: "VIDEO";
  caption: string;
  provider: "YOUTUBE" | "VIMEO";
  embed: string | null;
};
type Data = {
  items: (Photo | Video)[];
  syncUrl: boolean;
  labels: Record<
    | "close"
    | "previous"
    | "next"
    | "video"
    | "playVideo"
    | "counter"
    | "videoConsentYoutube"
    | "videoConsentVimeo",
    string
  >;
};

const ICONS = {
  x: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x size-6" aria-hidden="true"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>',
  left: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left size-6" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>',
  right:
    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right size-6" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>',
  play: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play size-5" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path></svg>',
};

function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  className = "",
  html = "",
): HTMLElementTagNameMap[K] {
  const e = document.createElement(tag);
  if (className) e.className = className;
  if (html) e.innerHTML = html;
  return e;
}

function img(
  src: string,
  srcset: string | null,
  alt: string,
  className: string,
  eager = false,
): HTMLImageElement {
  const i = document.createElement("img");
  i.alt = alt;
  if (eager) i.loading = "eager";
  i.decoding = "async";
  i.setAttribute("data-nimg", "fill");
  if (className) i.className = className;
  i.setAttribute(
    "style",
    "position:absolute;height:100%;width:100%;left:0;top:0;right:0;bottom:0;color:transparent",
  );
  if (srcset) {
    i.sizes = "100vw";
    i.srcset = srcset;
  }
  i.src = src;
  return i;
}

export function initGalleries(): void {
  document.querySelectorAll<HTMLUListElement>("ul[data-gallery]").forEach((list) => {
    if (!once("gallery", list)) return;
    const id = list.dataset.gallery!;
    const dialog = document.querySelector<HTMLDialogElement>(`dialog[data-gallery-dialog="${id}"]`);
    const script = document.querySelector<HTMLScriptElement>(`script[data-gallery-data="${id}"]`);
    if (!dialog || !script) return;
    const data = JSON.parse(script.textContent || "{}") as Data;
    const tiles = [...list.querySelectorAll<HTMLButtonElement>("[data-gallery-open]")];
    let index: number | null = null;
    let playing = false;
    let pointerStart: number | null = null;

    const setUrl = () => {
      if (!data.syncUrl) return;
      const url = new URL(window.location.href);
      if (index === null) url.searchParams.delete("foto");
      else url.searchParams.set("foto", String(index + 1));
      window.history.replaceState(window.history.state, "", url);
    };

    const render = () => {
      if (index === null) {
        dialog.replaceChildren();
        return;
      }
      const items = data.items;
      const current = items[index]!;
      const next = items[(index + 1) % items.length]!;
      const wrap = el("div", "flex h-full flex-col");
      wrap.addEventListener("pointerdown", (e) => (pointerStart = e.clientX));
      wrap.addEventListener("pointerup", (e) => {
        if (pointerStart === null) return;
        const dx = e.clientX - pointerStart;
        pointerStart = null;
        if (Math.abs(dx) > 60) go(dx < 0 ? 1 : -1);
      });

      const top = el("div", "flex items-center justify-between gap-4 p-3 md:p-5");
      const close = el(
        "button",
        "flex size-12 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/25 hover:bg-white/20",
        ICONS.x,
      );
      close.type = "button";
      close.append(Object.assign(el("span", "sr-only"), { textContent: data.labels.close }));
      close.addEventListener("click", () => dialog.close());
      const counter = el("p", "text-small font-semibold tabular-nums");
      counter.setAttribute("aria-live", "polite");
      counter.textContent = data.labels.counter
        .replace("{current}", String(index + 1))
        .replace("{total}", String(items.length));
      top.append(close, counter);

      const stage = el("div", "relative flex min-h-0 flex-1 items-center justify-center px-2 md:px-20");
      if (current.type === "PHOTO") {
        const box = el("div", "relative h-full max-h-[80dvh] w-full");
        box.append(img(current.src, current.srcset, current.alt, "object-contain"));
        stage.append(box);
      } else if (playing && current.embed) {
        const iframe = el("iframe", "aspect-video w-full max-w-5xl rounded-md");
        iframe.src = current.embed;
        iframe.title = current.caption || data.labels.video;
        iframe.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
        stage.append(iframe);
      } else {
        const facade = el("div", "max-w-md rounded-xl bg-white/5 p-8 text-center ring-1 ring-white/15");
        facade.append(
          Object.assign(el("p", "text-lead"), { textContent: current.caption || data.labels.video }),
        );
        facade.append(
          Object.assign(el("p", "mt-3 text-small text-mist"), {
            textContent:
              current.provider === "YOUTUBE"
                ? data.labels.videoConsentYoutube
                : data.labels.videoConsentVimeo,
          }),
        );
        const play = el(
          "button",
          "mt-6 inline-flex min-h-12 items-center gap-2 rounded-full bg-white px-6 font-semibold text-himalaya hover:bg-cream",
          ICONS.play,
        );
        play.type = "button";
        play.append(document.createTextNode(data.labels.playVideo));
        play.addEventListener("click", () => {
          playing = true;
          render();
        });
        facade.append(play);
        stage.append(facade);
      }
      if (items.length > 1) {
        const prev = el(
          "button",
          "absolute top-1/2 left-2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-deep/70 ring-1 ring-white/25 hover:bg-deep md:left-5",
          ICONS.left,
        );
        prev.type = "button";
        prev.append(Object.assign(el("span", "sr-only"), { textContent: data.labels.previous }));
        prev.addEventListener("click", () => go(-1));
        const nxt = el(
          "button",
          "absolute top-1/2 right-2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-deep/70 ring-1 ring-white/25 hover:bg-deep md:right-5",
          ICONS.right,
        );
        nxt.type = "button";
        nxt.append(Object.assign(el("span", "sr-only"), { textContent: data.labels.next }));
        nxt.addEventListener("click", () => go(1));
        stage.append(prev, nxt);
      }

      const bottom = el("div", "mx-auto w-full max-w-3xl px-4 pt-3 pb-6 text-center");
      if (current.caption)
        bottom.append(Object.assign(el("p", "text-body"), { textContent: current.caption }));
      wrap.append(top, stage, bottom);
      // Preload only the next photo.
      if (next.type === "PHOTO" && next.id !== current.id) {
        const pre = el("div", "pointer-events-none absolute size-px overflow-hidden opacity-0");
        pre.setAttribute("aria-hidden", "true");
        pre.append(img(next.src, next.srcset, "", "", true));
        wrap.append(pre);
      }
      dialog.replaceChildren(wrap);
    };

    const open = (i: number) => {
      index = i;
      playing = false;
      render();
      setUrl();
      if (!dialog.open) dialog.showModal();
      dialog.querySelector<HTMLButtonElement>("button")?.focus();
    };
    const go = (delta: number) => {
      if (index === null) return;
      playing = false;
      index = (index + delta + data.items.length) % data.items.length;
      render();
      setUrl();
      dialog.querySelector<HTMLButtonElement>("button")?.focus();
    };

    tiles.forEach((tile) => tile.addEventListener("click", () => open(Number(tile.dataset.galleryOpen))));
    dialog.addEventListener("close", () => {
      const i = index;
      index = null;
      playing = false;
      render();
      setUrl();
      if (i !== null) tiles[i]?.focus();
    });
    dialog.addEventListener("keydown", (e) => {
      if (e.key === "ArrowRight") go(1);
      if (e.key === "ArrowLeft") go(-1);
    });
    dialog.addEventListener("click", (e) => {
      if (e.target === dialog) dialog.close();
    });

    // Open from ?foto=N (shared link).
    if (data.syncUrl) {
      const n = Number(new URLSearchParams(window.location.search).get("foto"));
      if (Number.isInteger(n) && n >= 1 && n <= data.items.length) requestAnimationFrame(() => open(n - 1));
    }
  });
}
