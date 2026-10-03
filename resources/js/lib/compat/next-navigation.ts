/**
 * Stand-in for `next/navigation` (Vite alias) backed by Inertia, so copied admin components work
 * unchanged: usePathname / useSearchParams / useRouter / useParams.
 */
import { router, usePage } from "@inertiajs/react";
import { useMemo } from "react";

export function usePathname(): string {
  const { url } = usePage();
  return url.split("?")[0]!.split("#")[0]!;
}

export function useSearchParams(): URLSearchParams {
  const { url } = usePage();
  return useMemo(() => new URLSearchParams(url.includes("?") ? url.slice(url.indexOf("?") + 1) : ""), [url]);
}

export function useParams<T extends Record<string, string>>(): T {
  const { props } = usePage<{ routeParams?: T }>();
  return (props.routeParams ?? {}) as T;
}

export function useRouter() {
  return useMemo(
    () => ({
      push: (href: string, opts?: { scroll?: boolean }) =>
        router.visit(href, { preserveScroll: opts?.scroll === false }),
      replace: (href: string, opts?: { scroll?: boolean }) =>
        router.visit(href, { replace: true, preserveScroll: opts?.scroll === false }),
      refresh: () => router.reload(),
      back: () => window.history.back(),
      forward: () => window.history.forward(),
      prefetch: () => undefined,
    }),
    [],
  );
}

/** Server-only in Next; on the client a full navigation is the closest equivalent. */
export function redirect(href: string): never {
  window.location.assign(href);
  throw new Error("redirect");
}

export function notFound(): never {
  throw new Error("notFound() is server-only; render a 404 from the Laravel controller instead");
}
