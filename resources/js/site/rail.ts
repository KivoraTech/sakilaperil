import { once } from "./once";

/** Horizontal card rails (port of components/site/ui/rail.tsx): prev/next buttons, no scroll-jacking. */
export function initRails(): void {
  document.querySelectorAll<HTMLElement>("[data-rail]").forEach((rail) => {
    if (!once("rail", rail)) return;
    const track = rail.querySelector<HTMLElement>("[role='region']");
    const prev = rail.querySelector<HTMLButtonElement>("[data-rail-prev]");
    const next = rail.querySelector<HTMLButtonElement>("[data-rail-next]");
    if (!track || !prev || !next) return;
    const update = () => {
      prev.disabled = track.scrollLeft <= 4;
      next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    };
    const scroll = (dir: 1 | -1) => {
      const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      track.scrollBy({ left: dir * track.clientWidth * 0.85, behavior: reduce ? "auto" : "smooth" });
    };
    update();
    track.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update);
    prev.addEventListener("click", () => scroll(-1));
    next.addEventListener("click", () => scroll(1));
  });
}
