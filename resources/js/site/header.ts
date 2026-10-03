/**
 * Header islands (port of components/site/layout/header-nav.tsx):
 * - scroll state: `data-scrolled` after 24px (CSS handles the transparent-on-hero look); with motion
 *   allowed the header slides away while reading down (past 80% of the first screen) and comes back on
 *   any upward scroll — never while something in it has focus or a menu is open;
 * - desktop dropdowns (click/keyboard disclosure, Escape, outside click);
 * - mobile menu: native <dialog> (focus trap, Escape, inert page), backdrop click closes, focus returns.
 */
import { motionAllowed } from "./motion";
import { once } from "./once";

export function initHeader(): void {
  const header = document.getElementById("site-header");
  if (!header) return;
  initScrollState();
  if (once("header", header)) header.addEventListener("focusin", () => header.removeAttribute("data-hidden"));
  header.querySelectorAll<HTMLLIElement>("li[data-dropdown]").forEach((li) => {
    if (once("dropdown", li)) initDropdown(li);
  });
  const dialog = header.querySelector("dialog[data-menu]");
  if (dialog && once("menu", dialog)) initMobileMenu(header);
}

let scrollBound = false;

function initScrollState() {
  if (scrollBound) return;
  scrollBound = true;
  const canHide = motionAllowed();
  let lastY = window.scrollY;
  let raf = 0;
  const update = () => {
    raf = 0;
    const header = document.getElementById("site-header");
    if (!header) return;
    const y = window.scrollY;
    header.toggleAttribute("data-scrolled", y > 24);
    if (canHide) {
      const delta = y - lastY;
      const busy =
        header.contains(document.activeElement) || !!header.querySelector("[open],[aria-expanded='true']");
      if (busy || y < window.innerHeight * 0.8 || delta < -4) header.removeAttribute("data-hidden");
      else if (delta > 6) header.setAttribute("data-hidden", "");
    }
    lastY = y;
  };
  update();
  window.addEventListener(
    "scroll",
    () => {
      if (!raf) raf = requestAnimationFrame(update);
    },
    { passive: true },
  );
}

function initDropdown(li: HTMLLIElement) {
  const button = li.querySelector<HTMLButtonElement>(":scope > button");
  const tpl = li.querySelector<HTMLTemplateElement>(":scope > template[data-dropdown-menu]");
  if (!button || !tpl) return;
  const icon = button.querySelector("svg");
  let menu: Element | null = null;
  const onDoc = (e: MouseEvent) => {
    if (!li.contains(e.target as Node)) setOpen(false);
  };
  const onKey = (e: KeyboardEvent) => {
    if (e.key === "Escape") {
      setOpen(false);
      button.focus();
    }
  };
  const setOpen = (open: boolean) => {
    button.setAttribute("aria-expanded", String(open));
    icon?.classList.toggle("rotate-180", open);
    if (open && !menu) {
      menu = tpl.content.firstElementChild!.cloneNode(true) as Element;
      li.appendChild(menu);
      document.addEventListener("click", onDoc);
      document.addEventListener("keydown", onKey);
    } else if (!open && menu) {
      menu.remove();
      menu = null;
      document.removeEventListener("click", onDoc);
      document.removeEventListener("keydown", onKey);
    }
  };
  button.addEventListener("click", () => setOpen(button.getAttribute("aria-expanded") !== "true"));
}

function initMobileMenu(header: HTMLElement) {
  const dialog = header.querySelector<HTMLDialogElement>("dialog[data-menu]");
  const trigger = header.querySelector<HTMLButtonElement>("[data-menu-open]");
  if (!dialog || !trigger) return;
  trigger.addEventListener("click", () => {
    dialog.showModal();
    trigger.setAttribute("aria-expanded", "true");
  });
  dialog.addEventListener("close", () => {
    trigger.setAttribute("aria-expanded", "false");
    trigger.focus();
  });
  dialog.addEventListener("click", (e) => {
    if (e.target === dialog) dialog.close();
  });
  dialog
    .querySelectorAll("[data-menu-close], nav a")
    .forEach((el) => el.addEventListener("click", () => dialog.close()));
}
