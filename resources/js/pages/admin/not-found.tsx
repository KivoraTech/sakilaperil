import { Head } from "@inertiajs/react";
import Link from "next/link";
import { Button } from "@/components/ui/button";

/** 404 inside the admin shell: a deleted item or a mistyped admin address (App\Http\Controllers\Admin\AdminNotFound). */
export default function AdminNotFoundPage({
  what = "page",
  backHref = "/admin",
  backLabel = "Back to dashboard",
}: {
  what?: string;
  backHref?: string;
  backLabel?: string;
}) {
  return (
    <div className="mx-auto max-w-lg rounded-xl border bg-background p-8 text-center">
      <Head title="Not found" />
      <h1 className="text-lg font-semibold">This {what} no longer exists</h1>
      <p className="mt-2 text-sm text-muted-foreground">
        It may have been deleted by a colleague, or the address is not correct.
      </p>
      <Button asChild className="mt-6">
        <Link prefetch={false} href={backHref}>
          {backLabel}
        </Link>
      </Button>
    </div>
  );
}
