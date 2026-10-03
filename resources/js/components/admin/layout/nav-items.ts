import type { Capability } from "@/lib/permissions";

export type AdminNavItem = {
  href: string;
  label: string;
  icon:
    | "dashboard"
    | "pages"
    | "news"
    | "projects"
    | "events"
    | "gallery"
    | "reports"
    | "media"
    | "submissions"
    | "donations"
    | "navigation"
    | "settings"
    | "users"
    | "audit";
  capability: Capability;
  group: AdminNavGroup;
};

/** Sidebar groups, in display order, named for what volunteers do rather than how the CMS works. */
export const adminNavGroups = [
  "Website",
  "Stories & activity",
  "Photos & files",
  "People & messages",
  "Setup",
] as const;
export type AdminNavGroup = (typeof adminNavGroups)[number];

/** Admin sections. The sidebar only shows items the user can access; pages re-check server-side. */
export const adminNav: AdminNavItem[] = [
  { href: "/admin", label: "Dashboard", icon: "dashboard", capability: "dashboard.view", group: "Website" },
  { href: "/admin/pages", label: "Pages", icon: "pages", capability: "pages.view", group: "Website" },
  // Label only: the URL stays /admin/navigation.
  {
    href: "/admin/navigation",
    label: "Menus",
    icon: "navigation",
    capability: "navigation.manage",
    group: "Website",
  },
  {
    href: "/admin/news",
    label: "News & stories",
    icon: "news",
    capability: "content.create",
    group: "Stories & activity",
  },
  {
    href: "/admin/projects",
    label: "Projects",
    icon: "projects",
    capability: "content.create",
    group: "Stories & activity",
  },
  {
    href: "/admin/events",
    label: "Events",
    icon: "events",
    capability: "content.create",
    group: "Stories & activity",
  },
  {
    href: "/admin/media",
    label: "Photos & files",
    icon: "media",
    capability: "media.upload",
    group: "Photos & files",
  },
  {
    href: "/admin/gallery",
    label: "Gallery albums",
    icon: "gallery",
    capability: "content.create",
    group: "Photos & files",
  },
  {
    href: "/admin/reports",
    label: "Reports & documents",
    icon: "reports",
    capability: "content.create",
    group: "Photos & files",
  },
  {
    href: "/admin/submissions",
    label: "Messages & sign-ups",
    icon: "submissions",
    capability: "submissions.read",
    group: "People & messages",
  },
  {
    href: "/admin/donations",
    label: "Donations",
    icon: "donations",
    capability: "donations.read",
    group: "People & messages",
  },
  {
    href: "/admin/settings",
    label: "Settings",
    icon: "settings",
    capability: "settings.manage",
    group: "Setup",
  },
  { href: "/admin/users", label: "Users", icon: "users", capability: "users.manage", group: "Setup" },
  { href: "/admin/audit", label: "Audit log", icon: "audit", capability: "audit.read", group: "Setup" },
];
