/**
 * Submissions + donations admin actions (ENGAGEMENT). Same names as the reference server actions
 * (app/admin/(shell)/{submissions,donations}/actions.ts) → POST /admin/actions/<area>/<verb>.
 */
import { callAction } from "./client";

export const setSubmissionStatus = (input: unknown) => callAction("submissions/set-status", input);
// keepalive: opening a message and going straight back to the inbox must still mark it as read.
export const markSubmissionRead = (input: unknown) =>
  callAction("submissions/mark-read", input, { keepalive: true });
export const saveInternalNote = (input: unknown) => callAction("submissions/save-note", input);
export const deleteSubmission = (input: unknown) => callAction("submissions/delete", input);

export const recordBankTransfer = (input: unknown) =>
  callAction<{ id: string }>("donations/record-bank-transfer", input);
export const setManualDonationStatus = (input: unknown) => callAction("donations/set-manual-status", input);
