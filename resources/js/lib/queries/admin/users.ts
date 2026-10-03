/** Types of the users admin data (reference: lib/queries/admin/users.ts). */
import type { Role } from "@/lib/permissions";

export type AdminUserRow = {
  id: string;
  name: string;
  email: string;
  role: Role;
  isActive: boolean;
  lastLoginAt: string | null;
  createdAt: string;
  activeSessions: number;
};
