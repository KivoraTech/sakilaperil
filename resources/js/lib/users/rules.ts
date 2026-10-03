import { z } from "zod";
import { canAssignRole, canManageUser, roles, type Role } from "@/lib/permissions";

/**
 * Mirrors PASSWORD_MIN/MAX_LENGTH in lib/auth/password.ts (not imported: that module loads the
 * native argon2 binding, and this file is also used by client forms). A unit test keeps them equal.
 */
export const PASSWORD_MIN_LENGTH = 12;
export const PASSWORD_MAX_LENGTH = 128;

/**
 * Pure user-management rules (unit-tested). The server actions in lib/actions/users.ts load
 * the actor/target from the DB and call these before changing anything.
 */

type Actor = { id: string; role: Role };
type Target = { id: string; role: Role; isActive: boolean };

export const roleSchema = z.enum(roles);

export const passwordSchema = z
  .string()
  .min(PASSWORD_MIN_LENGTH, `Use at least ${PASSWORD_MIN_LENGTH} characters`)
  .max(PASSWORD_MAX_LENGTH);

export const createUserSchema = z.object({
  name: z.string().trim().min(1, "Enter a name").max(100),
  email: z.string().trim().toLowerCase().pipe(z.email("Enter a valid email").max(200)),
  role: roleSchema,
  /** Empty = generate a temporary password and show it once. */
  password: z.union([passwordSchema, z.literal("")]).optional(),
});
export type CreateUserInput = z.input<typeof createUserSchema>;

export const userIdSchema = z.object({ userId: z.string().min(1).max(64) });
export const changeRoleSchema = userIdSchema.extend({ role: roleSchema });
export const setActiveSchema = userIdSchema.extend({ active: z.boolean() });
export const renameSchema = userIdSchema.extend({ name: z.string().trim().min(1).max(100) });
export const resetPasswordSchema = userIdSchema.extend({
  password: z.union([passwordSchema, z.literal("")]).optional(),
});

export type RuleResult = { ok: true } | { ok: false; code: "FORBIDDEN" | "CONFLICT"; message: string };
const allow: RuleResult = { ok: true };
const deny = (message: string, code: "FORBIDDEN" | "CONFLICT" = "FORBIDDEN"): RuleResult => ({
  ok: false,
  code,
  message,
});

export function checkCreate(actor: Actor, role: Role): RuleResult {
  if (!canAssignRole(actor.role, role)) return deny("You cannot create users with this role.");
  return allow;
}

/**
 * @param activeSuperAdmins number of ACTIVE super admins right now (including the target).
 */
export function checkRoleChange(
  actor: Actor,
  target: Target,
  newRole: Role,
  activeSuperAdmins: number,
): RuleResult {
  if (actor.id === target.id) return deny("You cannot change your own role. Ask another admin.");
  if (!canManageUser(actor, target)) return deny("You cannot manage this user.");
  if (!canAssignRole(actor.role, newRole)) return deny("You cannot assign this role.");
  if (target.role === "SUPER_ADMIN" && newRole !== "SUPER_ADMIN" && target.isActive && activeSuperAdmins <= 1)
    return deny("This is the last super admin. Make someone else super admin first.", "CONFLICT");
  return allow;
}

export function checkSetActive(
  actor: Actor,
  target: Target,
  active: boolean,
  activeSuperAdmins: number,
): RuleResult {
  if (actor.id === target.id && !active) return deny("You cannot deactivate your own account.");
  if (!canManageUser(actor, target)) return deny("You cannot manage this user.");
  if (!active && target.role === "SUPER_ADMIN" && target.isActive && activeSuperAdmins <= 1)
    return deny("This is the last active super admin and cannot be deactivated.", "CONFLICT");
  return allow;
}

/** Revoke sessions / reset password / rename: self is always allowed, others need canManageUser. */
export function checkManageOrSelf(actor: Actor, target: Target): RuleResult {
  if (actor.id === target.id) return allow;
  return canManageUser(actor, target) ? allow : deny("You cannot manage this user.");
}

const ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";

/** Readable random temporary password (no ambiguous characters), 20 chars ≈ 115 bits. */
export function generateTemporaryPassword(randomBytes: (n: number) => Uint8Array): string {
  const bytes = randomBytes(40);
  let out = "";
  for (const b of bytes) {
    if (b >= 232) continue; // 232 = 4 × 58 → unbiased modulo
    out += ALPHABET[b % ALPHABET.length];
    if (out.length === 20) break;
  }
  if (out.length < 20) return generateTemporaryPassword(randomBytes);
  return `${out.slice(0, 5)}-${out.slice(5, 10)}-${out.slice(10, 15)}-${out.slice(15, 20)}`;
}
