import { once } from "./once";

/** Pause/play for the scrolling-words strip (port of components/site/motion/marquee-pause.tsx, WCAG 2.2.2). */
export function initMarquee(): void {
  document.querySelectorAll<HTMLButtonElement>("[data-marquee-pause]").forEach((btn) => {
    if (!once("marquee", btn)) return;
    const label = btn.querySelector(".sr-only");
    const tplPause = btn.querySelector<HTMLTemplateElement>("template[data-icon-pause]");
    const tplPlay = btn.querySelector<HTMLTemplateElement>("template[data-icon-play]");
    btn.addEventListener("click", () => {
      const paused = btn.getAttribute("aria-pressed") !== "true";
      btn.setAttribute("aria-pressed", String(paused));
      btn.closest(".marquee")?.toggleAttribute("data-paused", paused);
      const icon = (paused ? tplPlay : tplPause)?.content.firstElementChild?.cloneNode(true);
      const current = btn.querySelector(":scope > svg");
      if (icon && current) current.replaceWith(icon);
      if (label) label.textContent = (paused ? btn.dataset.labelPlay : btn.dataset.labelPause) ?? "";
    });
  });
}
