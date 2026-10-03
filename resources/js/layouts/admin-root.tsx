import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { FlashToasts } from "@/layouts/flash-toasts";

/** Port of app/admin/layout.tsx (body classes live on <body> in resources/views/admin.blade.php). */
export function AdminRoot({ children }: { children: React.ReactNode }) {
  return (
    <>
      <TooltipProvider>{children}</TooltipProvider>
      {/*
        Bottom-right (top-right toasts covered the editors' Save/Publish buttons) and lifted above the
        sticky "Save settings" bar: a toast sitting on a button swallows the click.
      */}
      <Toaster richColors position="bottom-right" offset={{ bottom: 76 }} mobileOffset={{ bottom: 76 }} />
      <FlashToasts />
    </>
  );
}
