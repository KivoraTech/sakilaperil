import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

/** Port of app/admin/login/{page,login-form}.tsx — same markup, posts to LoginController. */
export default function LoginPage({ next, status }: { next: string | null; status?: string | null }) {
  const form = useForm({ email: "", password: "", next: next ?? "" });
  // LoginController returns one generic message under `form` (no account enumeration).
  const errors = form.errors as Record<string, string | undefined>;
  const error = errors.form ?? errors.email;
  return (
    <main className="flex min-h-screen items-center justify-center bg-paper p-4">
      <Head title="Sign in" />
      <div className="w-full max-w-sm rounded-2xl border bg-white p-8 shadow-sm">
        <div className="mb-6 flex flex-col items-center gap-3 text-center">
          <img
            src="/logo.png"
            alt=""
            width={56}
            height={56}
            fetchPriority="high"
            decoding="async"
            style={{ color: "transparent" }}
          />
          <h1 className="font-serif text-2xl font-semibold text-himalaya">Sakila per il Nepal</h1>
          <p className="text-sm text-muted-foreground">Sign in to manage the website</p>
        </div>
        {status ? (
          <p role="status" className="mb-4 rounded-md bg-muted p-3 text-sm">
            {status}
          </p>
        ) : null}
        <form
          className="grid gap-4"
          noValidate
          onSubmit={(e) => {
            e.preventDefault();
            form.post("/admin/login", {
              preserveScroll: true,
              onFinish: () => form.reset("password"),
            });
          }}
        >
          {next ? <input type="hidden" name="next" value={next} /> : null}
          <div className="grid gap-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              name="email"
              type="email"
              autoComplete="username"
              required
              value={form.data.email}
              onChange={(e) => form.setData("email", e.target.value)}
              aria-invalid={!!errors.email}
              aria-describedby={error ? "login-error" : undefined}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              value={form.data.password}
              onChange={(e) => form.setData("password", e.target.value)}
              aria-invalid={!!errors.password}
              aria-describedby={error ? "login-error" : undefined}
            />
          </div>
          {error ? (
            <p id="login-error" role="alert" className="text-sm text-destructive">
              {error}
            </p>
          ) : null}
          <Button type="submit" size="lg" disabled={form.processing} className="mt-2">
            {form.processing ? "Signing in…" : "Sign in"}
          </Button>
          <Link
            href="/admin/forgot-password"
            className="text-center text-sm text-muted-foreground underline underline-offset-4"
          >
            Forgot your password?
          </Link>
        </form>
      </div>
    </main>
  );
}
