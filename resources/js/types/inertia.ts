import type { CurrentUser } from "@/lib/auth/session";
import type { Capability } from "@/lib/permissions";

/** Props shared with every Inertia page by App\Http\Middleware\HandleInertiaRequests. */
export type SharedProps = {
  auth: { user: CurrentUser | null; capabilities: Capability[] };
  flash: { success?: string | null; error?: string | null; id?: string | null };
  errors: Record<string, string>;
  [key: string]: unknown;
};
