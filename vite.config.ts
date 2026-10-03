import { fileURLToPath, URL } from "node:url";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

const r = (p: string) => fileURLToPath(new URL(p, import.meta.url));

export default defineConfig({
  plugins: [
    laravel({
      // site.ts = public-site islands (vanilla TS), admin.tsx = Inertia admin (React),
      // preview-bridge.ts = the page editor's live-preview iframe helper (ADMIN).
      input: [
        "resources/css/app.css",
        // The public site's own, smaller stylesheet (only the utilities public templates use).
        "resources/css/site.css",
        "resources/js/site.ts",
        "resources/js/admin.tsx",
        "resources/js/preview-bridge.ts",
        // Engagement islands (forms, newsletter, donate widget), loaded only where used (ENGAGEMENT).
        "resources/js/site/engagement/index.ts",
      ],
      refresh: true,
    }),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      "@": r("./resources/js"),
      // Components copied from the Next.js reference keep their imports (ADR-012).
      "next/link": r("./resources/js/lib/compat/next-link.tsx"),
      "next/navigation": r("./resources/js/lib/compat/next-navigation.ts"),
      "next/image": r("./resources/js/lib/compat/next-image.tsx"),
      "next-themes": r("./resources/js/lib/compat/next-themes.ts"),
    },
  },
  build: { sourcemap: false, chunkSizeWarningLimit: 900 },
});
