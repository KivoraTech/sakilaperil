import { ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";
import { can, roleLabels } from "@/lib/permissions";
import type { CurrentUser } from "@/lib/auth/session";
import { AdminSidebar } from "./admin-sidebar";
import { MobileNav } from "./mobile-nav";
import { adminNav } from "./nav-items";
import { UserMenu } from "./user-menu";

export function AdminShell({ user, children }: { user: CurrentUser; children: React.ReactNode }) {
  const items = adminNav.filter((i) => can(user.role, i.capability));
  return (
    <div className="flex min-h-screen">
      <a
        href="#admin-main"
        className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2"
      >
        Skip to content
      </a>
      <AdminSidebar items={items} />
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b bg-background/95 px-4 backdrop-blur">
          <div className="flex items-center gap-2">
            <MobileNav items={items} />
            <span className="text-sm text-muted-foreground lg:hidden">Sakila CMS</span>
          </div>
          <div className="flex items-center gap-2">
            <Button asChild variant="outline" size="sm">
              <a href="/it" target="_blank" rel="noopener noreferrer">
                <ExternalLink aria-hidden />
                <span className="hidden sm:inline">View website</span>
                <span className="sr-only sm:hidden">View website (opens in a new tab)</span>
              </a>
            </Button>
            <UserMenu name={user.name} email={user.email} roleLabel={roleLabels[user.role]} />
          </div>
        </header>
        <main id="admin-main" className="flex-1 p-4 md:p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
