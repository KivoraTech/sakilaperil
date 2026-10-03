import { Head } from "@inertiajs/react";
import Link from "next/link";
import { Button } from "@/components/ui/button";

/** Port of app/admin/(shell)/access-denied/page.tsx. */
export default function AccessDeniedPage() {
  return (
    <div className="mx-auto max-w-lg rounded-xl border bg-background p-8 text-center">
      <Head title="Access denied" />
      <h1 className="text-lg font-semibold">You don&apos;t have access to this section</h1>
      <p className="mt-2 text-sm text-muted-foreground">Ask an administrator if you need this permission.</p>
      <Button asChild className="mt-6">
        <Link prefetch={false} href="/admin">
          Back to dashboard
        </Link>
      </Button>
    </div>
  );
}
