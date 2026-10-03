/**
 * Public-site islands entry (vanilla TS; loaded on every public page). Ports of the reference's
 * client components, enhancing the server-rendered Blade HTML (resources/views/site/**):
 * motion controller, header (scroll state, dropdowns, mobile menu), count-up, rails, gallery
 * lightbox, marquee pause, click-to-load maps, copy link, sticky donate bar, report filters.
 * ENGAGEMENT owns the form/donate islands (resources/js/site/engagement/*).
 */
import { initCountUp } from "./site/count-up";
import { initGalleries } from "./site/gallery";
import { initHeader } from "./site/header";
import { initMarquee } from "./site/marquee";
import { initMotion } from "./site/motion";
import { initRails } from "./site/rail";
import { initCopyLinks, initMapEmbeds, initReportFilters, initStickyDonate } from "./site/small";

// Make the fonts addressable with Vite::asset() for <link rel="preload"> (resources/views/partials/font-preload.blade.php).
import.meta.glob(["../fonts/**"]);

let stopMotion: () => void = () => {};

/** Idempotent: safe to call again after the preview swaps the page content in place. */
function boot() {
  initHeader();
  initCountUp();
  stopMotion();
  stopMotion = initMotion();
  initRails();
  initGalleries();
  initMarquee();
  initMapEmbeds();
  initCopyLinks();
  initStickyDonate();
  initReportFilters();
}

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot, { once: true });
else boot();

// The admin live preview (resources/js/preview-bridge.ts) re-renders the page in place, then fires this.
document.addEventListener("sakila:content-swapped", boot);
