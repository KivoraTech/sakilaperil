"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  CalendarDays,
  FileText,
  FolderKanban,
  HandHeart,
  Images,
  Inbox,
  LayoutDashboard,
  Library,
  Menu as MenuIcon,
  Newspaper,
  ScrollText,
  Settings,
  Users,
  FileBarChart,
} from "lucide-react";
import { cn } from "@/lib/utils";
import type { AdminNavItem } from "./nav-items";

const icons: Record<AdminNavItem["icon"], React.ComponentType<{ className?: string }>> = {
  dashboard: LayoutDashboard,
  pages: FileText,
  news: Newspaper,
  projects: FolderKanban,
  events: CalendarDays,
  gallery: Images,
  reports: FileBarChart,
  media: Library,
  submissions: Inbox,
  donations: HandHeart,
  navigation: MenuIcon,
  settings: Settings,
  users: Users,
  audit: ScrollText,
};

export function isActive(pathname: string, href: string) {
  return href === "/admin" ? pathname === "/admin" : pathname === href || pathname.startsWith(`${href}/`);
}

export function AdminNavList({ items, onNavigate }: { items: AdminNavItem[]; onNavigate?: () => void }) {
  const pathname = usePathname();
  const groups = [...new Set(items.map((i) => i.group))];
  return (
    <nav aria-label="Admin" className="grid gap-5">
      {groups.map((group) => (
        <div key={group}>
          <p className="px-3 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
            {group}
          </p>
          <ul className="grid gap-0.5">
            {items
              .filter((i) => i.group === group)
              .map((item) => {
                const Icon = icons[item.icon];
                const active = isActive(pathname, item.href);
                return (
                  <li key={item.href}>
                    <Link
                      prefetch={false}
                      href={item.href}
                      onClick={onNavigate}
                      aria-current={active ? "page" : undefined}
                      className={cn(
                        "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-muted",
                        active ? "bg-primary/10 text-primary" : "text-foreground/80",
                      )}
                    >
                      <Icon className="size-4 shrink-0" aria-hidden />
                      {item.label}
                    </Link>
                  </li>
                );
              })}
          </ul>
        </div>
      ))}
    </nav>
  );
}

export function AdminBrand() {
  return (
    <Link prefetch={false} href="/admin" className="flex items-center gap-2 px-3">
      <Image src="/logo.png" alt="" width={32} height={32} />
      <span className="font-serif text-base leading-tight font-semibold text-himalaya">
        Sakila <span className="block text-xs font-normal text-muted-foreground">CMS</span>
      </span>
    </Link>
  );
}

export function AdminSidebar({ items }: { items: AdminNavItem[] }) {
  return (
    <aside className="hidden w-64 shrink-0 border-r bg-background lg:block">
      <div className="sticky top-0 flex h-screen flex-col gap-6 overflow-y-auto py-5">
        <AdminBrand />
        <div className="px-2">
          <AdminNavList items={items} />
        </div>
      </div>
    </aside>
  );
}
