/**
 * The public site's ONE motion island (port of components/site/motion/motion-controller.tsx,
 * design §7 "Production motion").
 *
 * - `[data-reveal]` (+ optional variant value) → adds `.is-visible` once, on entering the viewport.
 * - `[data-loop]` (flags, marquee, scroll cue) → `data-playing` only while on screen.
 * - `[data-parallax]` / `[data-hero-scroll]` → CSS scroll-driven animations where supported;
 *   otherwise one shared, passive rAF loop writes `--progress` (0..1).
 *
 * Progressive enhancement: the server HTML is the final state. Nothing is hidden until this runs,
 * elements already on screen are shown immediately, and it does nothing under
 * prefers-reduced-motion or when the editor disabled motion (no `data-motion` on `.site`).
 */

/** True when decorative motion may run (site setting on + no reduced-motion preference). */
export function motionAllowed(): boolean {
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return false;
  return !!document.querySelector("[data-motion]");
}

export function initMotion(): () => void {
  const enabled = !!document.querySelector("[data-motion]");
  if (!enabled) return () => {};
  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
  if (reduced.matches || !("IntersectionObserver" in window)) return () => {};

  const root = document.documentElement;
  const inView = (el: Element) => {
    const r = el.getBoundingClientRect();
    return r.top < window.innerHeight && r.bottom > 0;
  };

  // ── Reveal once ──────────────────────────────────────────────────────────
  // Clip-path wipes, the ridge draw and the stamp start with ZERO visible area, which the
  // IntersectionObserver would never report as intersecting, so they are watched through
  // their parent box instead.
  const waiting = new Map<Element, Set<Element>>();
  const revealIO = new IntersectionObserver(
    (entries) => {
      for (const e of entries) {
        if (!e.isIntersecting) continue;
        waiting.get(e.target)?.forEach((el) => el.classList.add("is-visible"));
        waiting.delete(e.target);
        revealIO.unobserve(e.target);
      }
    },
    { rootMargin: "0px 0px -8% 0px", threshold: 0.08 },
  );
  const trackReveal = (el: Element) => {
    if (el.classList.contains("is-visible")) return;
    const variant = el.getAttribute("data-reveal") ?? "";
    const watch = (/^(wipe|draw|stamp)/.test(variant) && el.parentElement) || el;
    // Above the fold on load: show without animating (no flash of hidden content).
    if (!root.classList.contains("js-reveal") && inView(watch)) {
      el.classList.add("is-visible");
      return;
    }
    const set = waiting.get(watch) ?? new Set<Element>();
    set.add(el);
    waiting.set(watch, set);
    revealIO.observe(watch);
  };

  // ── Loops play only while visible ────────────────────────────────────────
  const loopIO = new IntersectionObserver((entries) => {
    for (const e of entries) e.target.toggleAttribute("data-playing", e.isIntersecting);
  });

  // ── Scroll-linked fallback (no CSS scroll timelines, e.g. Firefox) ───────
  const scrollTimelines = CSS.supports?.("animation-timeline: view()") ?? false;
  const active = new Set<HTMLElement>();
  let raf = 0;
  const frame = () => {
    raf = 0;
    const vh = window.innerHeight;
    for (const el of active) {
      const r = el.getBoundingClientRect();
      // Hero: 0 at the top of the page → 1 when it has scrolled away. Others: 0 entering → 1 leaving.
      const p = el.hasAttribute("data-hero-scroll") ? -r.top / r.height : (vh - r.top) / (vh + r.height);
      el.style.setProperty("--progress", Math.min(1, Math.max(0, p)).toFixed(4));
    }
  };
  const schedule = () => {
    if (!raf && active.size) raf = requestAnimationFrame(frame);
  };
  const scrollIO = new IntersectionObserver((entries) => {
    for (const e of entries) {
      if (e.isIntersecting) active.add(e.target as HTMLElement);
      else active.delete(e.target as HTMLElement);
    }
    schedule();
  });
  if (!scrollTimelines) {
    root.classList.add("js-scroll-fallback");
    window.addEventListener("scroll", schedule, { passive: true });
    window.addEventListener("resize", schedule, { passive: true });
  }

  const scan = (scope: ParentNode) => {
    scope.querySelectorAll("[data-reveal]").forEach(trackReveal);
    scope.querySelectorAll("[data-loop]").forEach((el) => loopIO.observe(el));
    if (!scrollTimelines) {
      scope.querySelectorAll("[data-parallax],[data-hero-scroll]").forEach((el) => scrollIO.observe(el));
    }
  };
  scan(document);
  root.classList.add("js-reveal");

  // Content added later (lightbox, filtered rows…): pick it up too.
  const mo = new MutationObserver((mutations) => {
    for (const m of mutations)
      m.addedNodes.forEach((n) => {
        if (!(n instanceof Element)) return;
        if (n.matches("[data-reveal]")) trackReveal(n);
        if (n.matches("[data-loop]")) loopIO.observe(n);
        if (!scrollTimelines && n.matches("[data-parallax],[data-hero-scroll]")) scrollIO.observe(n);
        scan(n);
      });
  });
  mo.observe(document.body, { childList: true, subtree: true });

  // If the visitor switches reduced motion on mid-visit, show everything and stop.
  const onReducedChange = () => {
    if (!reduced.matches) return;
    document.querySelectorAll("[data-reveal]").forEach((el) => el.classList.add("is-visible"));
    cleanup();
  };
  reduced.addEventListener("change", onReducedChange);

  function cleanup() {
    revealIO.disconnect();
    waiting.clear();
    loopIO.disconnect();
    scrollIO.disconnect();
    mo.disconnect();
    cancelAnimationFrame(raf);
    window.removeEventListener("scroll", schedule);
    window.removeEventListener("resize", schedule);
    reduced.removeEventListener("change", onReducedChange);
    root.classList.remove("js-reveal", "js-scroll-fallback");
    document.querySelectorAll("[data-playing]").forEach((el) => el.removeAttribute("data-playing"));
  }
  return cleanup;
}
