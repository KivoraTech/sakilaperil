import { z } from "zod";
import { SAFE_HREF } from "@/lib/blocks/fields";
import { routeKeys } from "@/lib/i18n/routes";

/** Menus the site renders (NavigationKey in lib/queries/public/navigation.ts). */
export const navigationKeys = ["header", "footer-explore", "footer-help", "footer-legal"] as const;
export type NavigationKeyName = (typeof navigationKeys)[number];

export const navigationLabels: Record<
  NavigationKeyName,
  { name: string; description: string; nested: boolean }
> = {
  header: { name: "Header menu", description: "Main navigation at the top of every page.", nested: true },
  "footer-explore": { name: "Footer · Explore", description: "First footer column.", nested: false },
  "footer-help": { name: "Footer · Get involved", description: "Second footer column.", nested: false },
  "footer-legal": { name: "Footer · Legal", description: "Privacy, cookies and legal links.", nested: false },
};

export const MAX_NAV_ITEMS = 40;

export const navItemSchema = z
  .object({
    label: z.object({
      it: z.string().trim().min(1, "Enter the Italian label").max(60),
      en: z.string().trim().max(60).optional(),
    }),
    linkType: z.enum(["PAGE", "ROUTE", "INTERNAL", "EXTERNAL"]),
    pageId: z.string().max(64).optional(),
    routeKey: z.string().max(40).optional(),
    href: z.string().trim().max(500).optional(),
    openInNewTab: z.boolean().default(false),
    isVisible: z.boolean().default(true),
    /** 0 = top level, 1 = child of the closest previous top-level item (header dropdowns). */
    depth: z.union([z.literal(0), z.literal(1)]).default(0),
  })
  .superRefine((v, ctx) => {
    switch (v.linkType) {
      case "PAGE":
        if (!v.pageId) ctx.addIssue({ code: "custom", path: ["pageId"], message: "Choose a page" });
        break;
      case "ROUTE":
        if (!v.routeKey || !(routeKeys as readonly string[]).includes(v.routeKey))
          ctx.addIssue({ code: "custom", path: ["routeKey"], message: "Choose a section of the site" });
        break;
      case "INTERNAL":
        if (!v.href || !/^[/#]/.test(v.href) || !SAFE_HREF.test(v.href))
          ctx.addIssue({
            code: "custom",
            path: ["href"],
            message: "Use a site path without the language, e.g. /projects or /chi-siamo#team",
          });
        break;
      case "EXTERNAL":
        if (!v.href || !/^https:\/\//.test(v.href) || !SAFE_HREF.test(v.href))
          ctx.addIssue({ code: "custom", path: ["href"], message: "Use a full https:// address" });
        break;
    }
  });
export type NavItemInput = z.input<typeof navItemSchema>;

export const saveNavigationSchema = z
  .object({
    key: z.enum(navigationKeys),
    items: z.array(navItemSchema).max(MAX_NAV_ITEMS, `A menu can have at most ${MAX_NAV_ITEMS} links`),
  })
  .superRefine((v, ctx) => {
    if (v.items[0] && v.items[0].depth !== 0)
      ctx.addIssue({
        code: "custom",
        path: ["items", 0, "depth"],
        message: "The first link cannot be nested",
      });
    if (!navigationLabels[v.key].nested)
      v.items.forEach((item, i) => {
        if (item.depth !== 0)
          ctx.addIssue({
            code: "custom",
            path: ["items", i, "depth"],
            message: "This menu does not support nesting",
          });
      });
  });
export type SaveNavigationInput = z.input<typeof saveNavigationSchema>;

export const routeKeyLabels: Record<string, string> = {
  projects: "Projects (list)",
  news: "News & stories (list)",
  events: "Events (calendar)",
  gallery: "Gallery (albums)",
  home: "Home page",
  about: "About us page",
  "get-involved": "Get involved page",
  transparency: "Transparency page",
  contact: "Contact page",
  privacy: "Privacy policy",
  cookies: "Cookie policy",
  donate: "Donate (Get involved → donate section)",
};
