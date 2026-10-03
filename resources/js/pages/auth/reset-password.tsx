import { Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AuthCard } from "./auth-card";

/** Choose a new password from an emailed link (PasswordResetController). */
export default function ResetPasswordPage({ token, valid }: { token: string; valid: boolean }) {
  const form = useForm({ token, password: "", passwordConfirmation: "" });
  const errors = form.errors as Record<string, string | undefined>;
  const mismatch =
    form.data.passwordConfirmation !== "" && form.data.passwordConfirmation !== form.data.password;
  const error = errors.form ?? errors.password ?? errors.passwordConfirmation;
  if (!valid) {
    return (
      <AuthCard title="Link expired" subtitle="This link has expired or was already used">
        <Link
          href="/admin/forgot-password"
          className="text-center text-sm font-medium underline underline-offset-4"
        >
          Request a new link
        </Link>
      </AuthCard>
    );
  }
  return (
    <AuthCard title="Choose a new password" subtitle="Use at least 12 characters">
      <form
        className="grid gap-4"
        noValidate
        onSubmit={(e) => {
          e.preventDefault();
          form.post("/admin/reset-password", {
            preserveScroll: true,
            onFinish: () => form.reset("password", "passwordConfirmation"),
          });
        }}
      >
        <div className="grid gap-2">
          <Label htmlFor="password">New password</Label>
          <Input
            id="password"
            name="password"
            type="password"
            autoComplete="new-password"
            required
            minLength={12}
            value={form.data.password}
            onChange={(e) => form.setData("password", e.target.value)}
            aria-invalid={!!error}
            aria-describedby={error ? "reset-error" : undefined}
          />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="passwordConfirmation">Repeat the new password</Label>
          <Input
            id="passwordConfirmation"
            name="passwordConfirmation"
            type="password"
            autoComplete="new-password"
            required
            value={form.data.passwordConfirmation}
            onChange={(e) => form.setData("passwordConfirmation", e.target.value)}
            aria-invalid={mismatch || !!errors.passwordConfirmation}
            aria-describedby={mismatch ? "reset-mismatch" : error ? "reset-error" : undefined}
          />
          {mismatch ? (
            <p id="reset-mismatch" className="text-sm text-destructive">
              The two passwords are not the same.
            </p>
          ) : null}
        </div>
        {error ? (
          <p id="reset-error" role="alert" className="text-sm text-destructive">
            {error}
          </p>
        ) : null}
        <Button type="submit" size="lg" disabled={form.processing || mismatch} className="mt-2">
          {form.processing ? "Saving…" : "Save new password"}
        </Button>
      </form>
    </AuthCard>
  );
}
