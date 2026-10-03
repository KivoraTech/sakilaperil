/**
 * Role → capability matrix (PLAN.md §7). Pure and client-safe: the admin UI may use it
 * to hide controls, but EVERY server action/route must re-check with
 * `requireCapability()` from lib/permissions/server.ts.
 */
export const roles = ["SUPER_ADMIN", "ADMIN", "EDITOR", "AUTHOR"] as const;
export type Role = (typeof roles)[number];

export function isRole(value: unknown): value is Role {
  return typeof value === "string" && (roles as readonly string[]).includes(value);
}

export const roleLabels: Record<Role, string> = {
  SUPER_ADMIN: "Super admin",
  ADMIN: "Admin",
  EDITOR: "Editor",
  AUTHOR: "Author",
};

export const capabilities = [
  "dashboard.view",
  // Users & settings
  "users.manage",
  "settings.manage",
  "navigation.manage",
  // Pages (page builder)
  "pages.view",
  "pages.editDraft", // edit sections/text of existing pages (AUTHOR: "Limited")
  "pages.create",
  "pages.publish",
  "pages.delete",
  // Collections: news, projects, events, gallery, reports
  "content.create",
  "content.editOwn", // edit items they authored (news posts)
  "content.editAll",
  "content.publish",
  "content.delete",
  // Media library
  "media.upload",
  "media.edit",
  "media.delete",
  // Engagement (contains personal data — GDPR data minimisation)
  "submissions.read",
  "submissions.manage",
  "donations.read",
  "donations.manage",
  // Audit log
  "audit.read",
] as const;
export type Capability = (typeof capabilities)[number];

const ALL = new Set<Capability>(capabilities);

const matrix: Record<Role, ReadonlySet<Capability>> = {
  SUPER_ADMIN: ALL,
  ADMIN: ALL,
  EDITOR: new Set<Capability>([
    "dashboard.view",
    "navigation.manage",
    "pages.view",
    "pages.editDraft",
    "pages.create",
    "pages.publish",
    "content.create",
    "content.editOwn",
    "content.editAll",
    "content.publish",
    "content.delete",
    "media.upload",
    "media.edit",
    "media.delete",
    "submissions.read",
    "audit.read",
  ]),
  AUTHOR: new Set<Capability>([
    "dashboard.view",
    "pages.view",
    "pages.editDraft",
    "content.create",
    "content.editOwn",
    "media.upload",
    "media.edit",
  ]),
};

export function can(role: Role | null | undefined, capability: Capability): boolean {
  if (!role) return false;
  return matrix[role]?.has(capability) ?? false;
}

export function capabilitiesFor(role: Role): Capability[] {
  return capabilities.filter((c) => matrix[role].has(c));
}

/**
 * Can `actor` edit a content item owned by `ownerId`? Editors+ may edit everything;
 * authors only their own.
 */
export function canEditContent(
  actor: { id: string; role: Role },
  ownerId: string | null | undefined,
): boolean {
  if (can(actor.role, "content.editAll")) return true;
  return can(actor.role, "content.editOwn") && !!ownerId && ownerId === actor.id;
}

const rank: Record<Role, number> = { SUPER_ADMIN: 3, ADMIN: 2, EDITOR: 1, AUTHOR: 0 };

/** Only SUPER_ADMIN may grant SUPER_ADMIN; admins may assign ADMIN and below. */
export function canAssignRole(actorRole: Role, targetRole: Role): boolean {
  if (!can(actorRole, "users.manage")) return false;
  if (targetRole === "SUPER_ADMIN") return actorRole === "SUPER_ADMIN";
  return rank[actorRole] >= rank[targetRole];
}

/**
 * Can `actor` modify/deactivate `target`? Nobody manages a higher-ranked user, and an
 * ADMIN cannot manage a SUPER_ADMIN. Users cannot change their own role (checked by caller
 * via `actor.id === target.id`).
 */
export function canManageUser(
  actor: { id: string; role: Role },
  target: { id: string; role: Role },
): boolean {
  if (!can(actor.role, "users.manage")) return false;
  if (target.role === "SUPER_ADMIN" && actor.role !== "SUPER_ADMIN") return false;
  return rank[actor.role] >= rank[target.role];
}
