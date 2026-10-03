/** User-management actions (reference: lib/actions/users.ts). */
import { callAction } from "./client";

export const createUserAction = (input: unknown) =>
  callAction<{ id: string; temporaryPassword: string | null }>("users/create", input);

export const changeUserRoleAction = (input: unknown) => callAction<null>("users/change-role", input);

export const setUserActiveAction = (input: unknown) => callAction<null>("users/set-active", input);

export const revokeUserSessionsAction = (input: unknown) =>
  callAction<{ count: number }>("users/revoke-sessions", input);

export const resetUserPasswordAction = (input: unknown) =>
  callAction<{ temporaryPassword: string | null }>("users/reset-password", input);

export const renameUserAction = (input: unknown) => callAction<null>("users/rename", input);
