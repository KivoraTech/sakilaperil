import Link from "next/link";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { navigationKeys, navigationLabels, type NavigationKeyName } from "@/lib/navigation/schema";
import { Head } from "@inertiajs/react";
import type { NavEditorData } from "@/lib/queries/admin/navigation";
import { cn } from "@/lib/utils";
import { NavigationEditor } from "./navigation-editor";

/** Port of app/admin/(shell)/navigation/page.tsx (data from NavigationController). */
export default function NavigationPage({ menu: menuParam, data }: { menu: string; data: NavEditorData }) {
  const menuKey: NavigationKeyName = (navigationKeys as readonly string[]).includes(menuParam)
    ? (menuParam as NavigationKeyName)
    : "header";
  const menu = data.menus.find((m) => m.key === menuKey)!;

  return (
    <>
      <Head title="Menus" />
      <AdminPageHeader
        title="Menus"
        description="The links in the top menu and the footer, in Italian and English."
      />
      <nav
        aria-label="Menus"
        className="mb-6 flex flex-wrap gap-1 rounded-lg border bg-background p-1 sm:inline-flex"
      >
        {navigationKeys.map((k) => (
          <Link
            prefetch={false}
            key={k}
            href={`/admin/navigation?menu=${k}`}
            aria-current={k === menuKey ? "page" : undefined}
            className={cn(
              "rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground",
              k === menuKey && "bg-primary text-primary-foreground hover:text-primary-foreground",
            )}
          >
            {navigationLabels[k].name}
            <span className="ml-1 text-xs opacity-80">
              ({data.menus.find((m) => m.key === k)?.items.length ?? 0})
            </span>
          </Link>
        ))}
      </nav>
      <NavigationEditor
        key={`${menuKey}:${JSON.stringify(menu.items).length}:${menu.items.map((i) => i.key).join()}`}
        menuKey={menuKey}
        initialItems={menu.items}
        pages={data.pages}
      />
    </>
  );
}
