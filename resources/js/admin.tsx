/**
 * Admin (CMS) entry: Inertia + React. Pages live in resources/js/pages/**; the server picks one with
 * Inertia::render('admin/dashboard', props). Every page except the login uses the persistent
 * AdminLayout (sidebar/header), like app/admin/(shell)/layout.tsx in the reference.
 */
import "../css/app.css";
import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import type { ComponentType, ReactNode } from "react";
import { AdminLayout } from "@/layouts/admin-layout";
import { AdminRoot } from "@/layouts/admin-root";

type PageModule = { default: ComponentType & { layout?: (page: ReactNode) => ReactNode } };
const pages = import.meta.glob<PageModule>("./pages/**/*.tsx");
import.meta.glob(["../fonts/**"]);

createInertiaApp({
  title: (title) => (title ? `${title} · Admin · Sakila` : "Admin · Sakila per il Nepal"),
  resolve: async (name) => {
    const loader = pages[`./pages/${name}.tsx`];
    if (!loader) throw new Error(`Unknown Inertia page: ${name}`);
    const page = await loader();
    const component = page.default;
    if (component.layout === undefined) {
      component.layout = name.startsWith("auth/")
        ? (p: ReactNode) => <AdminRoot>{p}</AdminRoot>
        : (p: ReactNode) => (
            <AdminRoot>
              <AdminLayout>{p}</AdminLayout>
            </AdminRoot>
          );
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: "#1f4d6b", delay: 250 },
});
