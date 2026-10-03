/**
 * Animated impact numbers (port of components/site/ui/count-up.tsx). The server-rendered text is
 * the FINAL value; it only counts up from 0 when the number starts off-screen and motion is allowed.
 * `data-count-delay` staggers a row of numbers.
 */
import { motionAllowed } from "./motion";
import { once } from "./once";

export function initCountUp(): void {
  const els = document.querySelectorAll<HTMLElement>("[data-count-up]");
  if (!els.length || !motionAllowed() || !("IntersectionObserver" in window)) return;
  const locale = document.documentElement.lang === "it" ? "it-IT" : "en-GB";
  const nf = new Intl.NumberFormat(locale);
  els.forEach((el) => {
    if (!once("count-up", el)) return;
    const value = Number(el.dataset.countUp);
    if (!Number.isFinite(value)) return;
    const r = el.getBoundingClientRect();
    if (r.top < window.innerHeight && r.bottom > 0) return;
    const delay = Number(el.dataset.countDelay ?? 0) || 0;
    const decimals = Number.isInteger(value) ? 0 : 1;
    const fmt = (n: number) => (decimals ? nf.format(Math.round(n * 10) / 10) : nf.format(Math.round(n)));
    el.textContent = fmt(0);
    const io = new IntersectionObserver(
      ([entry]) => {
        if (!entry?.isIntersecting) return;
        io.disconnect();
        const start = performance.now() + delay;
        const duration = 1600;
        const tick = (now: number) => {
          const t = Math.min(1, Math.max(0, now - start) / duration);
          el.textContent = fmt(value * (1 - Math.pow(1 - t, 3)));
          if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      },
      { threshold: 0.4 },
    );
    io.observe(el);
  });
}
