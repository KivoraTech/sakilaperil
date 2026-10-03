import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { Head } from "@inertiajs/react";
import type { Role } from "@/lib/permissions";
import type { AdminUserRow } from "@/lib/queries/admin/users";
import { UsersManager } from "./users-manager";

type Props = { users: AdminUserRow[]; activeSuperAdmins: number; actor: { id: string; role: Role } };

/** Port of app/admin/(shell)/users/page.tsx (data from UsersController). */
export default function UsersPage({ users, activeSuperAdmins, actor }: Props) {
  return (
    <>
      <Head title="Users" />
      <AdminPageHeader title="Users" description="Team members, their roles and sessions." />
      <UsersManager
        users={users}
        actor={{ id: actor.id, role: actor.role }}
        activeSuperAdmins={activeSuperAdmins}
      />
    </>
  );
}
