import { toast } from "sonner";
import { fail, type ActionResult } from "./result";

const SESSION_EXPIRED = "Not saved: your session has expired.";

/**
 * The session ended while the editor was open. What was typed is still on screen, so offer a way to
 * sign in WITHOUT leaving the page (a new tab shares the session and CSRF cookies); then saving works.
 */
function sessionExpired<T>(): ActionResult<T> {
  toast.error("Your session has expired", {
    id: "session-expired",
    description:
      "Sign in again in a new tab, then come back here and save. Your changes are kept on this page.",
    duration: 60_000,
    action: { label: "Sign in", onClick: () => window.open("/admin/login", "_blank") },
  });
  return fail("UNAUTHENTICATED", SESSION_EXPIRED);
}

/**
 * Transport for the admin "server actions" of the reference app. Each lib/actions/<area>.ts keeps the
 * reference's exported function names/signatures and calls:
 *
 *   export const savePageDraftAction = (input: SaveDraftInput) =>
 *     callAction<{ version: string }>("pages/save-draft", input);
 *
 * → POST /admin/actions/pages/save-draft (JSON, CSRF via the XSRF-TOKEN cookie) → Laravel returns an
 * ActionResult. Copied React components therefore need no changes at their call sites.
 */
export async function callAction<T = undefined>(
  name: string,
  input?: unknown,
  /**
   * `keepalive`: the request is finished even if the user leaves the page right away. Only for
   * small fire-and-forget actions (browsers cap keepalive bodies at 64 KB).
   */
  options: { keepalive?: boolean } = {},
): Promise<ActionResult<T>> {
  let res: Response;
  try {
    res = await fetch(`/admin/actions/${name}`, {
      method: "POST",
      credentials: "same-origin",
      keepalive: options.keepalive === true,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") ?? "",
      },
      body: JSON.stringify(input ?? null),
    });
  } catch {
    return fail("ERROR", "Network error. Check your connection and try again.");
  }
  if (res.status === 419 || res.status === 401) return sessionExpired<T>();
  try {
    return (await res.json()) as ActionResult<T>;
  } catch {
    return fail("ERROR", "Something went wrong. Please try again.");
  }
}

/** Same as callAction for multipart uploads (FormData). */
export async function callActionForm<T = undefined>(name: string, form: FormData): Promise<ActionResult<T>> {
  let res: Response;
  try {
    res = await fetch(`/admin/actions/${name}`, {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") ?? "" },
      body: form,
    });
  } catch {
    return fail("ERROR", "Network error. Check your connection and try again.");
  }
  if (res.status === 419 || res.status === 401) return sessionExpired<T>();
  try {
    return (await res.json()) as ActionResult<T>;
  } catch {
    return fail("ERROR", "Something went wrong. Please try again.");
  }
}

function readCookie(name: string): string | null {
  const match = document.cookie.split("; ").find((c) => c.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}
