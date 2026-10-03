/**
 * Small islands: click-to-load map (ui/map-embed.tsx), copy link (ui/copy-link.tsx), sticky donate bar
 * (donate/sticky-donate-bar.tsx) and transparency report filters (reports/report-list.tsx).
 */
import { once } from "./once";

export function initMapEmbeds(): void {
  document.querySelectorAll<HTMLElement>("[data-map-embed]").forEach((box) => {
    if (!once("map", box)) return;
    box.querySelector("[data-map-load]")?.addEventListener("click", () => {
      const iframe = document.createElement("iframe");
      iframe.src = box.dataset.mapEmbed ?? "";
      iframe.title = box.dataset.mapTitle ?? "";
      iframe.className = "absolute inset-0 size-full border-0";
      iframe.loading = "lazy";
      box.replaceChildren(iframe);
    });
  });
}

export function initCopyLinks(): void {
  document.querySelectorAll<HTMLButtonElement>("[data-copy-link]").forEach((btn) => {
    if (!once("copy-link", btn)) return;
    const live = btn.querySelector<HTMLElement>("[data-copied-label]");
    const tpl = btn.querySelector<HTMLTemplateElement>("template[data-icon-copied]");
    btn.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copyLink ?? location.href);
      } catch {
        return; // clipboard blocked: the URL is still in the address bar
      }
      const icon = btn.querySelector(":scope > svg");
      const original = icon?.cloneNode(true);
      const check = tpl?.content.firstElementChild?.cloneNode(true);
      if (icon && check) icon.replaceWith(check);
      if (live) live.textContent = live.dataset.copiedLabel ?? "";
      setTimeout(() => {
        const now = btn.querySelector(":scope > svg");
        if (now && original) now.replaceWith(original);
        if (live) live.textContent = "";
      }, 2500);
    });
  });
}

export function initStickyDonate(): void {
  const bar = document.querySelector<HTMLElement>("[data-sticky-donate]");
  const target = bar ? document.getElementById(bar.dataset.stickyDonate ?? "") : null;
  if (!bar || !target || !("IntersectionObserver" in window) || !once("sticky-donate", bar)) return;
  const link = bar.querySelector<HTMLAnchorElement>("a");
  const footer = document.querySelector("footer");
  let pastTarget = false;
  let footerVisible = false;
  const sync = () => {
    const show = pastTarget && !footerVisible;
    bar.classList.toggle("translate-y-0", show);
    bar.classList.toggle("pointer-events-none", !show);
    bar.classList.toggle("translate-y-full", !show);
    bar.setAttribute("aria-hidden", String(!show));
    if (link) {
      if (show) link.removeAttribute("tabindex");
      else link.tabIndex = -1;
    }
  };
  new IntersectionObserver(([e]) => {
    pastTarget = !!e && !e.isIntersecting && e.boundingClientRect.top < 0;
    sync();
  }).observe(target);
  if (footer)
    new IntersectionObserver(([e]) => {
      footerVisible = !!e?.isIntersecting;
      sync();
    }).observe(footer);
}

export function initReportFilters(): void {
  document.querySelectorAll<HTMLElement>("[data-report-list]").forEach((list) => {
    if (!once("reports", list)) return;
    const select = list.querySelector<HTMLSelectElement>("[data-report-year]");
    const typeButtons = [...list.querySelectorAll<HTMLButtonElement>("[data-report-type]")];
    const rows = [...list.querySelectorAll<HTMLElement>("[data-report-row]")];
    const showing = list.querySelector<HTMLElement>("[data-report-showing]");
    const ul = rows[0]?.parentElement ?? list.querySelector("ul");
    const emptyTpl = list.querySelector<HTMLTemplateElement>("template[data-report-empty]");
    let year = "all";
    let type = "all";
    let empty: Element | null = null;
    const apply = () => {
      let count = 0;
      for (const r of rows) {
        const visible =
          (year === "all" || r.dataset.year === year) && (type === "all" || r.dataset.type === type);
        r.hidden = !visible;
        if (visible) count++;
      }
      typeButtons.forEach((b) => {
        const active = b.dataset.reportType === type;
        b.setAttribute("aria-pressed", String(active));
        b.classList.toggle("bg-himalaya", active);
        b.classList.toggle("text-white", active);
        b.classList.toggle("ring-himalaya", active);
        b.classList.toggle("bg-white", !active);
        b.classList.toggle("text-himalaya", !active);
        b.classList.toggle("ring-line-strong", !active);
        b.classList.toggle("hover:bg-cream", !active);
      });
      if (showing) {
        const d = list.dataset;
        const n = new Intl.NumberFormat(document.documentElement.lang === "it" ? "it-IT" : "en-GB").format(
          count,
        );
        showing.textContent =
          count === 0
            ? (d.showingZero ?? "")
            : count === 1
              ? (d.showingOne ?? "")
              : (d.showingOther ?? "").replace(/987[.,]?654[.,]?321/, n);
      }
      if (!count && !empty && emptyTpl && ul) {
        empty = emptyTpl.content.firstElementChild!.cloneNode(true) as Element;
        ul.appendChild(empty);
      } else if (count && empty) {
        empty.remove();
        empty = null;
      }
    };
    select?.addEventListener("change", () => {
      year = select.value;
      apply();
    });
    typeButtons.forEach((b) =>
      b.addEventListener("click", () => {
        type = b.dataset.reportType ?? "all";
        apply();
      }),
    );
  });
}
