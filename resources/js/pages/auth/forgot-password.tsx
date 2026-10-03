import { Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AuthCard } from "./auth-card";

/** Request a password-reset link (PasswordResetController). Same answer for every email (no enumeration). */
export default function ForgotPasswordPage() {
  const form = useForm({ email: "" });
  const errors = form.errors as Record<string, string | undefined>;
  const error = errors.form ?? errors.email;
  return (
    <AuthCard title="Reset password" subtitle="We'll email you a link to choose a new password">
      <form
        className="grid gap-4"
        noValidate
        onSubmit={(e) => {
          e.preventDefault();
          form.post("/admin/forgot-password", { preserveScroll: true });
        }}
      >
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
            aria-invalid={!!error}
            aria-describedby={error ? "reset-error" : undefined}
          />
        </div>
        {error ? (
          <p id="reset-error" role="alert" className="text-sm text-destructive">
            {error}
          </p>
        ) : null}
        <Button type="submit" size="lg" disabled={form.processing} className="mt-2">
          {form.processing ? "Sending…" : "Send reset link"}
        </Button>
        <Link
          href="/admin/login"
          className="text-center text-sm text-muted-foreground underline underline-offset-4"
        >
          Back to sign in
        </Link>
      </form>
    </AuthCard>
  );
}
