"use client";

import { useState } from "react";
import { Menu } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { AdminBrand, AdminNavList } from "./admin-sidebar";
import type { AdminNavItem } from "./nav-items";

export function MobileNav({ items }: { items: AdminNavItem[] }) {
  const [open, setOpen] = useState(false);
  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <Button variant="ghost" size="icon" className="lg:hidden" aria-label="Open navigation">
          <Menu />
        </Button>
      </SheetTrigger>
      <SheetContent side="left" className="w-72 overflow-y-auto p-4">
        <SheetHeader className="px-0">
          <SheetTitle className="sr-only">Navigation</SheetTitle>
          <AdminBrand />
        </SheetHeader>
        <AdminNavList items={items} onNavigate={() => setOpen(false)} />
      </SheetContent>
    </Sheet>
  );
}
