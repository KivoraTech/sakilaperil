import { usePage } from "@inertiajs/react";
import { AdminShell } from "@/components/admin/layout/admin-shell";
import type { SharedProps } from "@/types/inertia";

/** Port of app/admin/(shell)/layout.tsx: the signed-in shell around every admin page. */
export function AdminLayout({ children }: { children: React.ReactNode }) {
  const { auth } = usePage<SharedProps>().props;
  if (!auth.user) return <>{children}</>;
  return <AdminShell user={auth.user}>{children}</AdminShell>;
}
