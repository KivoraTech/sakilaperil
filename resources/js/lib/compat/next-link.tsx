/**
 * Stand-in for `next/link` (Vite alias) so components copied from the Next.js reference keep
 * their exact markup. Admin URLs navigate with Inertia (SPA); everything else is a plain <a>.
 * `prefetch` is accepted and ignored (admin prefetching was disabled in the reference anyway).
 */
import { Link as InertiaLink } from "@inertiajs/react";
import { forwardRef, type AnchorHTMLAttributes, type ReactNode } from "react";

type Href = string | { pathname?: string; query?: Record<string, string | number | undefined> };

export type LinkProps = Omit<AnchorHTMLAttributes<HTMLAnchorElement>, "href"> & {
  href: Href;
  prefetch?: boolean | null | "auto";
  replace?: boolean;
  scroll?: boolean;
  children?: ReactNode;
};

export function hrefToString(href: Href): string {
  if (typeof href === "string") return href;
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(href.query ?? {})) if (v !== undefined) qs.set(k, String(v));
  const q = qs.toString();
  return `${href.pathname ?? ""}${q ? `?${q}` : ""}`;
}

const Link = forwardRef<HTMLAnchorElement, LinkProps>(function Link(
  { href, prefetch: _prefetch, replace, scroll, target, children, ...rest },
  ref,
) {
  const url = hrefToString(href);
  const inertia =
    url.startsWith("/admin") && !url.startsWith("/admin/logout") && (!target || target === "_self");
  if (inertia) {
    return (
      <InertiaLink
        ref={ref}
        href={url}
        replace={replace}
        preserveScroll={scroll === false}
        // Plain anchor attributes only; Inertia-specific callbacks (onProgress…) are never passed by callers.
        {...(rest as object)}
        onClick={rest.onClick as ((event: React.MouseEvent<Element>) => void) | undefined}
      >
        {children}
      </InertiaLink>
    );
  }
  return (
    <a ref={ref} href={url} target={target} {...rest}>
      {children}
    </a>
  );
});

export default Link;
