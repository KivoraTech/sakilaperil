import type { ReactNode } from "react";
import { Head } from "@inertiajs/react";

/** Shared frame of the sign-in, forgot-password and reset-password screens (same look as login). */
export function AuthCard({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle: string;
  children: ReactNode;
}) {
  return (
    <main className="flex min-h-screen items-center justify-center bg-paper p-4">
      <Head title={title} />
      <div className="w-full max-w-sm rounded-2xl border bg-white p-8 shadow-sm">
        <div className="mb-6 flex flex-col items-center gap-3 text-center">
          <img
            src="/logo.png"
            alt=""
            width={56}
            height={56}
            decoding="async"
            style={{ color: "transparent" }}
          />
          <h1 className="font-serif text-2xl font-semibold text-himalaya">Sakila per il Nepal</h1>
          <p className="text-sm text-muted-foreground">{subtitle}</p>
        </div>
        {children}
      </div>
    </main>
  );
}
