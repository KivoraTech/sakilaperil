import type { Role } from "@/lib/permissions";

/** The signed-in admin user, shared by HandleInertiaRequests as `auth.user`. */
export type CurrentUser = {
  id: string;
  name: string;
  email: string;
  role: Role;
  image: string | null;
};
