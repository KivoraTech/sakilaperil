/**
 * Uniform return type of admin actions (same as sakila/lib/actions/result.ts). React Hook Form maps
 * `fieldErrors` onto inputs. Produced by Laravel (App\Http\Actions\ActionResult) — never raw errors.
 */
export type ActionResult<T = undefined> =
  | { ok: true; data: T; message?: string }
  | {
      ok: false;
      code:
        "VALIDATION" | "UNAUTHENTICATED" | "FORBIDDEN" | "NOT_FOUND" | "CONFLICT" | "RATE_LIMITED" | "ERROR";
      message: string;
      fieldErrors?: Record<string, string[]>;
    };

export type ActionErrorCode = Extract<ActionResult, { ok: false }>["code"];

export function ok<T>(data: T, message?: string): ActionResult<T> {
  return { ok: true, data, message };
}

export function fail(
  code: ActionErrorCode,
  message: string,
  fieldErrors?: Record<string, string[]>,
): Extract<ActionResult, { ok: false }> {
  return { ok: false, code, message, fieldErrors };
}
