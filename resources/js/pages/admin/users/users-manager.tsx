"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  Copy,
  KeyRound,
  LogOut,
  MoreHorizontal,
  Pencil,
  Shield,
  UserPlus,
  UserRoundCheck,
  UserRoundX,
} from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { ConfirmDialog } from "@/components/admin/page-builder/confirm-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
  changeUserRoleAction,
  createUserAction,
  renameUserAction,
  resetUserPasswordAction,
  revokeUserSessionsAction,
  setUserActiveAction,
} from "@/lib/actions/users";
import type { ActionResult } from "@/lib/actions/result";
import { canAssignRole, canManageUser, roleLabels, roles, type Role } from "@/lib/permissions";
import type { AdminUserRow } from "@/lib/queries/admin/users";
import { createUserSchema, type CreateUserInput } from "@/lib/users/rules";

const roleHelp: Record<Role, string> = {
  SUPER_ADMIN: "Everything, including managing other admins.",
  ADMIN: "Everything except managing super admins.",
  EDITOR: "Create, edit and publish all content; menus; read submissions and the audit log.",
  AUTHOR: "Write drafts and news; cannot publish or change settings.",
};

function fmt(iso: string | null) {
  return iso
    ? new Intl.DateTimeFormat("en-GB", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Europe/Rome",
      }).format(new Date(iso))
    : "Never";
}

type Dialogs =
  | null
  | { kind: "role"; user: AdminUserRow }
  | { kind: "rename"; user: AdminUserRow }
  | { kind: "password"; user: AdminUserRow }
  | { kind: "sessions"; user: AdminUserRow }
  | { kind: "deactivate"; user: AdminUserRow }
  | { kind: "secret"; email: string; password: string };

export function UsersManager({
  users,
  actor,
  activeSuperAdmins,
}: {
  users: AdminUserRow[];
  actor: { id: string; role: Role };
  activeSuperAdmins: number;
}) {
  const router = useRouter();
  const [dialog, setDialog] = useState<Dialogs>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [pending, start] = useTransition();
  const [role, setRole] = useState<Role>("EDITOR");
  const [name, setName] = useState("");

  const run = <T,>(fn: () => Promise<ActionResult<T>>, after?: (data: T) => void) =>
    start(async () => {
      const r = await fn();
      if (!r.ok) {
        toast.error(r.message);
        return;
      }
      toast.success(r.message ?? "Done");
      setDialog(null);
      after?.(r.data);
      router.refresh();
    });

  const assignable = roles.filter((r) => canAssignRole(actor.role, r));

  return (
    <>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          {users.filter((u) => u.isActive).length} active · {activeSuperAdmins} super admin
          {activeSuperAdmins === 1 ? "" : "s"}
        </p>
        <Button onClick={() => setCreateOpen(true)}>
          <UserPlus aria-hidden /> Add user
        </Button>
      </div>

      <div className="overflow-x-auto rounded-xl border bg-background">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>User</TableHead>
              <TableHead>Role</TableHead>
              <TableHead className="hidden md:table-cell">Last sign-in</TableHead>
              <TableHead className="hidden lg:table-cell">Sessions</TableHead>
              <TableHead className="text-right">
                <span className="sr-only">Actions</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {users.map((u) => {
              const self = u.id === actor.id;
              const manageable = self || canManageUser(actor, u);
              return (
                <TableRow key={u.id} className={u.isActive ? undefined : "opacity-60"}>
                  <TableCell>
                    <div className="font-medium">
                      {u.name}{" "}
                      {self ? <span className="text-xs font-normal text-muted-foreground">(you)</span> : null}
                    </div>
                    <div className="text-xs text-muted-foreground">{u.email}</div>
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-wrap gap-1.5">
                      <Badge variant={u.role === "SUPER_ADMIN" ? "default" : "secondary"} className="gap-1">
                        {u.role === "SUPER_ADMIN" ? <Shield className="size-3" aria-hidden /> : null}
                        {roleLabels[u.role]}
                      </Badge>
                      {!u.isActive ? <Badge variant="outline">Deactivated</Badge> : null}
                    </div>
                  </TableCell>
                  <TableCell className="hidden text-sm text-muted-foreground md:table-cell">
                    {fmt(u.lastLoginAt)}
                  </TableCell>
                  <TableCell className="hidden text-sm text-muted-foreground tabular-nums lg:table-cell">
                    {u.activeSessions}
                  </TableCell>
                  <TableCell className="text-right">
                    {manageable ? (
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`Actions for ${u.name}`}
                            disabled={pending}
                          >
                            <MoreHorizontal aria-hidden />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                          <DropdownMenuItem
                            onSelect={() => {
                              setName(u.name);
                              setDialog({ kind: "rename", user: u });
                            }}
                          >
                            <Pencil aria-hidden /> Rename
                          </DropdownMenuItem>
                          {!self ? (
                            <DropdownMenuItem
                              onSelect={() => {
                                setRole(u.role);
                                setDialog({ kind: "role", user: u });
                              }}
                            >
                              <Shield aria-hidden /> Change role
                            </DropdownMenuItem>
                          ) : null}
                          <DropdownMenuItem onSelect={() => setDialog({ kind: "password", user: u })}>
                            <KeyRound aria-hidden /> Reset password
                          </DropdownMenuItem>
                          <DropdownMenuItem onSelect={() => setDialog({ kind: "sessions", user: u })}>
                            <LogOut aria-hidden /> Sign out everywhere
                          </DropdownMenuItem>
                          {!self ? (
                            <>
                              <DropdownMenuSeparator />
                              {u.isActive ? (
                                <DropdownMenuItem
                                  variant="destructive"
                                  onSelect={() => setDialog({ kind: "deactivate", user: u })}
                                >
                                  <UserRoundX aria-hidden /> Deactivate
                                </DropdownMenuItem>
                              ) : (
                                <DropdownMenuItem
                                  onSelect={() =>
                                    run(() => setUserActiveAction({ userId: u.id, active: true }))
                                  }
                                >
                                  <UserRoundCheck aria-hidden /> Reactivate
                                </DropdownMenuItem>
                              )}
                            </>
                          ) : null}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    ) : (
                      <span className="text-xs text-muted-foreground">Protected</span>
                    )}
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </div>

      <CreateUserDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        assignable={assignable}
        onCreated={(email, password) => {
          setCreateOpen(false);
          if (password) setDialog({ kind: "secret", email, password });
          router.refresh();
        }}
      />

      {/* Change role */}
      <Dialog open={dialog?.kind === "role"} onOpenChange={(o) => !o && setDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Change role{dialog?.kind === "role" ? ` · ${dialog.user.name}` : ""}</DialogTitle>
            <DialogDescription>
              They will be signed out and get the new permissions when they sign in again.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-1.5">
            <Label htmlFor="role-select">Role</Label>
            <Select value={role} onValueChange={(v) => setRole(v as Role)}>
              <SelectTrigger id="role-select" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {assignable.map((r) => (
                  <SelectItem key={r} value={r}>
                    {roleLabels[r]}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">{roleHelp[role]}</p>
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setDialog(null)}>
              Cancel
            </Button>
            <Button
              disabled={pending}
              onClick={() =>
                dialog?.kind === "role" && run(() => changeUserRoleAction({ userId: dialog.user.id, role }))
              }
            >
              Change role
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Rename */}
      <Dialog open={dialog?.kind === "rename"} onOpenChange={(o) => !o && setDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Rename user</DialogTitle>
            <DialogDescription>The name is shown in the admin and the audit log.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-1.5">
            <Label htmlFor="rename-input">Name</Label>
            <Input id="rename-input" value={name} maxLength={100} onChange={(e) => setName(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setDialog(null)}>
              Cancel
            </Button>
            <Button
              disabled={pending || !name.trim()}
              onClick={() =>
                dialog?.kind === "rename" && run(() => renameUserAction({ userId: dialog.user.id, name }))
              }
            >
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={dialog?.kind === "password"}
        onOpenChange={(o) => !o && setDialog(null)}
        title="Reset password?"
        description="A new temporary password is generated and shown once. They are signed out everywhere and must use the new password."
        confirmLabel="Reset password"
        onConfirm={() => {
          if (dialog?.kind !== "password") return;
          const { id, email } = dialog.user;
          run(
            () => resetUserPasswordAction({ userId: id }),
            (d) => d.temporaryPassword && setDialog({ kind: "secret", email, password: d.temporaryPassword }),
          );
        }}
      />
      <ConfirmDialog
        open={dialog?.kind === "sessions"}
        onOpenChange={(o) => !o && setDialog(null)}
        title="Sign out everywhere?"
        description={
          dialog?.kind === "sessions" && dialog.user.id === actor.id
            ? "You will be signed out of every device, including this one."
            : "All their active sessions end immediately (e.g. a lost phone)."
        }
        confirmLabel="Sign out everywhere"
        onConfirm={() =>
          dialog?.kind === "sessions" && run(() => revokeUserSessionsAction({ userId: dialog.user.id }))
        }
      />
      <ConfirmDialog
        open={dialog?.kind === "deactivate"}
        onOpenChange={(o) => !o && setDialog(null)}
        title="Deactivate this user?"
        description="They are signed out immediately and cannot sign in. Their content and history are kept. You can reactivate them later."
        confirmLabel="Deactivate"
        destructive
        onConfirm={() =>
          dialog?.kind === "deactivate" &&
          run(() => setUserActiveAction({ userId: dialog.user.id, active: false }))
        }
      />

      {/* One-time temporary password */}
      <Dialog open={dialog?.kind === "secret"} onOpenChange={(o) => !o && setDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Temporary password</DialogTitle>
            <DialogDescription>
              Shown only once. Share it with {dialog?.kind === "secret" ? dialog.email : "the user"} through a
              safe channel (not in the same email as the address), and ask them to change it.
            </DialogDescription>
          </DialogHeader>
          {dialog?.kind === "secret" ? (
            <div className="flex items-center gap-2">
              <code
                className="flex-1 rounded-md border bg-muted px-3 py-2 font-mono text-sm"
                data-testid="temporary-password"
              >
                {dialog.password}
              </code>
              <Button
                variant="outline"
                size="icon"
                aria-label="Copy password"
                onClick={() =>
                  navigator.clipboard?.writeText(dialog.password).then(() => toast.success("Copied"))
                }
              >
                <Copy aria-hidden />
              </Button>
            </div>
          ) : null}
          <DialogFooter>
            <Button onClick={() => setDialog(null)}>Done</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function CreateUserDialog({
  open,
  onOpenChange,
  assignable,
  onCreated,
}: {
  open: boolean;
  onOpenChange: (o: boolean) => void;
  assignable: Role[];
  onCreated: (email: string, temporaryPassword: string | null) => void;
}) {
  const [pending, start] = useTransition();
  const form = useForm<CreateUserInput>({
    resolver: zodResolver(createUserSchema),
    defaultValues: { name: "", email: "", role: "AUTHOR", password: "" },
  });
  const submit = form.handleSubmit((values) =>
    start(async () => {
      const r = await createUserAction(values);
      if (!r.ok) {
        for (const [k, v] of Object.entries(r.fieldErrors ?? {}))
          form.setError(k as keyof CreateUserInput, { message: v[0] });
        toast.error(r.message);
        return;
      }
      toast.success(`${values.email} can now sign in`);
      form.reset();
      onCreated(String(values.email).toLowerCase(), r.data.temporaryPassword);
    }),
  );
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add a team member</DialogTitle>
          <DialogDescription>
            Public sign-up is disabled — only people you add here can sign in. Leave the password empty to
            generate a temporary one.
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={submit} noValidate className="grid gap-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Name</FormLabel>
                  <FormControl>
                    <Input {...field} autoComplete="off" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input {...field} type="email" autoComplete="off" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="role"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Role</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {assignable.map((r) => (
                        <SelectItem key={r} value={r}>
                          {roleLabels[r]}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>{roleHelp[field.value as Role]}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="password"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Password (optional)</FormLabel>
                  <FormControl>
                    <Input {...field} type="password" autoComplete="new-password" />
                  </FormControl>
                  <FormDescription>
                    At least 12 characters. Empty = generate a temporary password.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={pending}>
                {pending ? "Adding…" : "Add user"}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
