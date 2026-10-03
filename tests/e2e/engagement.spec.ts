import { expect, test, type Page } from "@playwright/test";

/**
 * ENGAGEMENT e2e: public forms → admin inbox, donate widget without payment keys, spam protection.
 * Requires a seeded DB. Each test uses its own X-Forwarded-For so the per-IP form rate limit never
 * trips across repeated runs (the app trusts the proxy header unless TRUST_PROXY=false).
 */
const ADMIN_EMAIL = process.env.SEED_ADMIN_EMAIL ?? "admin@sakila.local";
const PASSWORD = process.env.SEED_ADMIN_PASSWORD ?? "ChangeMe-Sakila-2026!";
const MIN_FILL_MS = 2600; // lib/spam/checks.ts MIN_FILL_MS + margin

const randomIp = () => `10.${rand(255)}.${rand(255)}.${rand(255)}`;
function rand(n: number) {
  return Math.floor(Math.random() * n);
}

test.use({ extraHTTPHeaders: { "x-forwarded-for": randomIp() } });
test.beforeEach(async ({ context }) => {
  await context.setExtraHTTPHeaders({ "x-forwarded-for": randomIp() });
});

async function loginAdmin(page: Page) {
  await page.goto("/admin/login");
  await page.getByLabel("Email").fill(ADMIN_EMAIL);
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

async function fillContactForm(page: Page, name: string) {
  await page.goto("/it/contatti");
  const form = page.locator('[data-engagement="contact-form"]');
  await expect(form).toBeVisible();
  await form.getByLabel("Nome e cognome").fill(name);
  await form.getByLabel("Email", { exact: true }).fill(`e2e-${Date.now()}@example.org`);
  await form.getByLabel("Messaggio").fill("Messaggio di prova automatica (e2e).");
  await form.getByRole("checkbox", { name: /informativa sulla privacy/ }).check();
  return form;
}

test.describe("engagement: forms", () => {
  test("contact form submission appears in the admin inbox", async ({ page }, info) => {
    // Unique per project: the desktop and mobile workers can start in the same millisecond.
    const name = `E2E Contatto ${Date.now()} ${info.project.name}`;
    const form = await fillContactForm(page, name);
    await page.waitForTimeout(MIN_FILL_MS);
    await form.getByRole("button", { name: "Invia messaggio" }).click();
    await expect(page.getByTestId("form-success")).toBeVisible();
    await expect(page.getByTestId("form-success")).toContainText("Grazie, E2E!");

    await loginAdmin(page);
    await page.goto(`/admin/submissions?type=contact&q=${encodeURIComponent(name)}`);
    const row = page.getByTestId("submission-row").filter({ hasText: name });
    await expect(row).toHaveCount(1);
    await expect(row).toHaveAttribute("data-unread", "true");

    // Detail view shows the message and marks it read.
    await row.getByRole("link").first().click();
    await expect(page.getByRole("heading", { level: 1, name })).toBeVisible();
    await expect(page.getByText("Messaggio di prova automatica (e2e).")).toBeVisible();
    await page.goto(`/admin/submissions?type=contact&q=${encodeURIComponent(name)}`);
    await expect(page.getByTestId("submission-row").filter({ hasText: name })).not.toHaveAttribute(
      "data-unread",
      "true",
    );
  });

  test("honeypot submissions are rejected and never stored", async ({ page }) => {
    const name = `E2E Bot ${Date.now()}`;
    const form = await fillContactForm(page, name);
    await form.locator('input[name="hp_extra"]').fill("http://spam.example", { force: true });
    await page.waitForTimeout(MIN_FILL_MS);
    await form.getByRole("button", { name: "Invia messaggio" }).click();
    await expect(form.getByRole("alert")).toContainText("Non siamo riusciti a inviare il modulo");
    await expect(page.getByTestId("form-success")).toHaveCount(0);

    await loginAdmin(page);
    await page.goto(`/admin/submissions?type=contact&view=all&q=${encodeURIComponent(name)}`);
    await expect(page.getByTestId("submission-row")).toHaveCount(0);
  });

  test("client-side validation lists errors and blocks submit", async ({ page }) => {
    await page.goto("/it/contatti");
    const form = page.locator('[data-engagement="contact-form"]');
    await form.getByRole("button", { name: "Invia messaggio" }).click();
    const summary = form.getByRole("alert");
    await expect(summary).toBeFocused();
    await expect(summary).toContainText("Campo obbligatorio");
    await expect(summary).toContainText("informativa sulla privacy");
    await expect(form.getByLabel("Nome e cognome")).toHaveAttribute("aria-invalid", "true");
  });

  test("API rejects cross-site, too-fast and invalid posts", async ({ request, baseURL }) => {
    const body = {
      name: "X",
      email: "x@example.org",
      message: "hi",
      consent: true,
      locale: "it",
      startedAt: Date.now() - 10_000,
    };
    const crossSite = await request.post("/api/forms/contact", {
      data: body,
      headers: { origin: "https://evil.example" },
    });
    expect(crossSite.status()).toBe(403);
    const tooFast = await request.post("/api/forms/contact", {
      data: { ...body, startedAt: Date.now() },
      headers: { origin: baseURL! },
    });
    expect(tooFast.status()).toBe(400);
    const invalid = await request.post("/api/forms/contact", {
      data: { ...body, email: "not-an-email", consent: false },
      headers: { origin: baseURL! },
    });
    expect(invalid.status()).toBe(422);
    expect((await invalid.json()).fieldErrors).toMatchObject({
      email: ["invalidEmail"],
      consent: ["consentRequired"],
    });
    const unknown = await request.post("/api/forms/unknown", {
      data: body,
      headers: { origin: baseURL! },
    });
    expect(unknown.status()).toBe(404);
  });

  test("newsletter sign-up is stored (pending) and listed for admins", async ({ page }) => {
    const email = `e2e-news-${Date.now()}@example.org`;
    await page.goto("/it/newsletter");
    const form = page.locator("main").locator('[data-engagement="newsletter-form"]');
    await form.getByLabel("Il tuo indirizzo email").fill(email);
    await form.getByRole("checkbox").check();
    await page.waitForTimeout(MIN_FILL_MS);
    await form.getByRole("button", { name: "Iscriviti" }).click();
    await expect(page.getByTestId("newsletter-success")).toBeVisible();

    await loginAdmin(page);
    await page.goto(`/admin/submissions?type=newsletter&q=${encodeURIComponent(email)}`);
    await expect(page.getByTestId("submission-row").filter({ hasText: email })).toContainText("Pending");
  });
});

test.describe("engagement: donations", () => {
  test("donate widget offers bank transfer when no payment keys are configured", async ({ page }) => {
    await page.goto("/it/donate");
    const widget = page.locator('[data-engagement="DonateWidget"]').first();
    await expect(widget).toBeVisible();
    // Providers that are not set up are not offered at all (no fake buttons, no "coming soon").
    await expect(widget.locator('[data-method="STRIPE"]')).toHaveCount(0);
    await expect(widget.locator('[data-method="PAYPAL"]')).toHaveCount(0);
    await expect(widget).not.toContainText("Non ancora disponibile");
    // With bank transfer alone there is nothing to choose: no method or frequency step, just the panel.
    await expect(widget.locator("[data-method]")).toHaveCount(0);
    await expect(widget.locator('[data-role="frequency"]')).toHaveCount(0);
    await expect(widget).not.toContainText("Stripe");
    const panel = widget.getByTestId("bank-transfer");
    await expect(panel).toBeVisible();
    await expect(panel).toContainText("Intestatario");
    await expect(panel).toContainText("Causale");
    // Seed IBAN is a [[TODO]] placeholder: it must never be shown; a pending notice is shown instead.
    await expect(panel).not.toContainText("[[TODO");
    await expect(panel).toContainText("in attesa di conferma");
  });

  test("donation API reports 'not configured' instead of failing", async ({ request, baseURL }) => {
    const res = await request.post("/api/donations", {
      data: {
        provider: "STRIPE",
        amount: 25,
        consent: true,
        locale: "it",
        startedAt: Date.now() - 10_000,
      },
      headers: { origin: baseURL! },
    });
    expect(res.status()).toBe(409);
    expect((await res.json()).code).toBe("NOT_CONFIGURED");
  });

  test("thank-you page never trusts the URL: unknown references show a neutral message", async ({ page }) => {
    await page.goto("/it/donate/thank-you?ref=SKL-2026-AAAAAAAA");
    await expect(page.getByTestId("donation-status")).toHaveAttribute("data-status", "UNKNOWN");
    await expect(page.getByTestId("donation-status")).toContainText("Non troviamo questa donazione");
  });

  test("admins see the donations list with provider status", async ({ page }) => {
    await loginAdmin(page);
    await page.goto("/admin/donations");
    await expect(page.getByRole("heading", { level: 1, name: "Donations" })).toBeVisible();
    await expect(page.getByLabel("Payment providers")).toContainText("Bank transfer: always on");
  });

  test("editors cannot open donations or submissions (personal data is admin-only)", async ({ page }) => {
    await page.goto("/admin/login");
    await page.getByLabel("Email").fill("editor@sakila.local");
    await page.getByLabel("Password").fill(PASSWORD);
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto("/admin/donations");
    await expect(page).toHaveURL(/\/admin\/access-denied/);
    await page.goto("/admin/submissions");
    await expect(page).toHaveURL(/\/admin\/access-denied/);
  });
});
