import { router } from "@inertiajs/react";

/**
 * `<form action={logoutAction}>` keeps working as in the reference: React 19 calls this client
 * function on submit; Inertia posts to Laravel (CSRF header added automatically) and follows the redirect.
 */
export function logoutAction(): void {
  router.post("/admin/logout");
}
