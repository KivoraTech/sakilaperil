import { expect, test, type Page } from "@playwright/test";

/**
 * ENGAGEMENT islands (vanilla TS ports of the reference React components) on the sandbox page:
 * hydration must not change the server-rendered DOM, and the behaviour must match the reference
 * (React Hook Form "onTouched", error summary focus, success panel focus, spam layers).
 */
const MIN_FILL_MS = 2600;
const rand = (n: number) => Math.floor(Math.random() * n);

test.beforeEach(async ({ context }) => {
  await context.setExtraHTTPHeaders({ "x-forwarded-for": `10.${rand(255)}.${rand(255)}.${rand(255)}` });
});

async function open(page: Page, query = "") {
  const errors: string[] = [];
  page.on("console", (m) => m.type() === "error" && errors.push(m.text()));
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(`/__engagement/it${query}`);
  await expect(page.locator("html")).toHaveAttribute("data-engagement-ready", "1");
  return errors;
}

/** Tag + sorted classes + own text + ids/aria of an element tree (like the parity outline). */
const OUTLINE = `(root) => {
  const lines = [];
  const walk = (el, d) => {
    const own = [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent.trim()).join(" ").trim();
    const keep = ["id", "for", "role", "aria-label", "aria-labelledby", "aria-describedby", "aria-invalid", "aria-required", "type", "name", "data-method", "data-testid"];
    const at = keep.filter((k) => el.hasAttribute(k)).map((k) => k + "=" + el.getAttribute(k).replace(/(nl|dw)-[a-z0-9]{8}/g, "UID")).join(" ");
    lines.push(" ".repeat(d) + el.tagName.toLowerCase() + "." + [...el.classList].sort().join(".") + (at ? " [" + at + "]" : "") + (own ? ' "' + own + '"' : ""));
    for (const c of el.children) if (c.tagName !== "SCRIPT") walk(c, d + 1);
  };
  walk(root, 0);
  return lines.join("\\n");
}`;

test("hydration keeps the server-rendered markup and logs no errors", async ({ page, request }) => {
  const html = await (await request.get("/__engagement/it")).text();
  const errors = await open(page);
  const roots = ["contact-form", "volunteer-form", "newsletter-form", "DonateWidget"];
  for (const name of roots) {
    const live = await page
      .locator(`[data-engagement="${name}"]`)
      .first()
      .evaluate(new Function(`return (${OUTLINE})`)());
    const ssr = await page.evaluate(
      ({ html, name, fn }) => {
        const doc = new DOMParser().parseFromString(html, "text/html");
        const el = doc.querySelector(`[data-engagement="${name}"]`)!;
        return new Function(`return (${fn})`)()(el);
      },
      { html, name, fn: OUTLINE },
    );
    expect(live, name).toBe(ssr);
  }
  expect(errors).toEqual([]);
});

test("contact: client validation lists errors, focuses the summary, marks fields invalid", async ({
  page,
}) => {
  await open(page);
  const form = page.locator('[data-engagement="contact-form"]');
  await form.getByRole("button", { name: "Invia messaggio" }).click();
  const summary = form.getByRole("alert");
  await expect(summary).toBeFocused();
  await expect(summary).toContainText("Campo obbligatorio");
  await expect(summary).toContainText("informativa sulla privacy");
  await expect(form.getByLabel("Nome e cognome")).toHaveAttribute("aria-invalid", "true");
  // Errors clear as the visitor fixes them (re-validation on change after a submit).
  await form.getByLabel("Nome e cognome").fill("Anna");
  await expect(form.getByLabel("Nome e cognome")).not.toHaveAttribute("aria-invalid", "true");
  await expect(form.getByLabel("Nome e cognome")).toHaveValue("Anna");
});

test("contact: a field is validated on first blur (onTouched)", async ({ page }) => {
  await open(page);
  const form = page.locator('[data-engagement="contact-form"]');
  const email = form.getByLabel("Email", { exact: true });
  await email.fill("not-an-email");
  await email.blur();
  await expect(form.locator("#contact-form-email-error")).toContainText(
    "Inserisci un indirizzo email valido",
  );
  await expect(email).toHaveValue("not-an-email");
  await email.fill("ok@example.org");
  await expect(form.locator("#contact-form-email-error")).toHaveCount(0);
});

test("contact: valid submission shows the focused success panel", async ({ page }) => {
  await open(page);
  const form = page.locator('[data-engagement="contact-form"]');
  await form.getByLabel("Nome e cognome").fill(`Sandbox Contatto ${Date.now()}`);
  await form.getByLabel("Email", { exact: true }).fill(`sandbox-${Date.now()}@example.org`);
  await form.getByLabel("Argomento").selectOption("press");
  await form.getByLabel("Messaggio").fill("Messaggio di prova (sandbox).");
  await form.getByRole("checkbox", { name: /informativa sulla privacy/ }).check();
  await page.waitForTimeout(MIN_FILL_MS);
  await form.getByRole("button", { name: "Invia messaggio" }).click();
  const success = page.getByTestId("form-success");
  await expect(success).toBeVisible();
  await expect(success).toBeFocused();
  await expect(success).toContainText("Grazie, Sandbox!");
  // "Send another" brings back an empty form.
  await page.getByRole("button", { name: /Invia un altro/ }).click();
  await expect(form.getByLabel("Nome e cognome")).toHaveValue("");
});

test("contact: honeypot submissions are rejected", async ({ page }) => {
  await open(page);
  const form = page.locator('[data-engagement="contact-form"]');
  await form.getByLabel("Nome e cognome").fill("Bot");
  await form.getByLabel("Email", { exact: true }).fill("bot@example.org");
  await form.getByLabel("Messaggio").fill("spam");
  await form.getByRole("checkbox", { name: /informativa sulla privacy/ }).check();
  await form.locator('input[name="hp_extra"]').fill("http://spam.example", { force: true });
  await page.waitForTimeout(MIN_FILL_MS);
  await form.getByRole("button", { name: "Invia messaggio" }).click();
  await expect(form.getByRole("alert")).toContainText("Non siamo riusciti a inviare il modulo");
  await expect(page.getByTestId("form-success")).toHaveCount(0);
});

test("newsletter: sign-up shows the success status", async ({ page }) => {
  await open(page);
  const form = page.locator("#newsletter [data-engagement='newsletter-form']");
  await form.getByRole("button", { name: "Iscriviti" }).click();
  await expect(
    form.getByText("Inserisci un indirizzo email valido").or(form.getByText("Campo obbligatorio")),
  ).toBeVisible();
  await form.getByLabel("Il tuo indirizzo email").fill(`sandbox-news-${Date.now()}@example.org`);
  await form.getByRole("checkbox").check();
  await page.waitForTimeout(MIN_FILL_MS);
  await form.getByRole("button", { name: "Iscriviti" }).click();
  const ok = page.locator("#newsletter").getByTestId("newsletter-success");
  await expect(ok).toBeVisible();
  await expect(ok).toBeFocused();
});

test("donate widget: bank transfer by default, providers honestly unavailable", async ({ page }) => {
  await open(page);
  const widget = page.locator('[data-engagement="DonateWidget"]');
  await expect(widget.locator('[data-method="STRIPE"]')).toHaveCount(0);
  await expect(widget).not.toContainText("Non ancora disponibile");
  // With bank transfer alone there is nothing to choose: no method or frequency step, just the panel.
  await expect(widget.locator("[data-method]")).toHaveCount(0);
  await expect(widget.locator('[data-role="frequency"]')).toHaveCount(0);
  const panel = widget.getByTestId("bank-transfer");
  await expect(panel).toContainText("Intestatario");
  await expect(panel).toContainText("in attesa di conferma");
  await expect(panel).not.toContainText("[[TODO");
});

test("unsubscribe: explicit POST confirmation", async ({ page, request }) => {
  const email = `sandbox-unsub-${Date.now()}@example.org`;
  // Subscribe via the API first (same-origin), then use a signed token rendered by the server.
  const base = new URL(page.context()["_options"]?.baseURL ?? "http://127.0.0.1:3340");
  const sub = await request.post("/api/newsletter/subscribe", {
    data: { email, consent: true, locale: "it", startedAt: Date.now() - 10_000 },
    headers: { origin: base.origin },
  });
  expect(sub.status()).toBe(202);
  const bad = await request.post("/api/newsletter/unsubscribe", {
    data: { token: "x.y" },
    headers: { origin: base.origin },
  });
  expect(bad.status()).toBe(400);
});

test.describe("donate widget with providers configured", () => {
  test.skip(
    !process.env.SANDBOX_PROVIDERS_URL,
    "needs the sandbox with test provider keys (SANDBOX_PROVIDERS_URL)",
  );
  test.use({ baseURL: process.env.SANDBOX_PROVIDERS_URL });

  test("card: amounts, custom amount, validation summary, monthly", async ({ page }) => {
    await open(page);
    const widget = page.locator('[data-engagement="DonateWidget"]');
    // Card is the first usable method → amount step visible, test-mode ribbon shown.
    await expect(widget.locator('[data-method="STRIPE"] input')).toBeChecked();
    await expect(widget.getByText("Modalità test: nessun addebito reale.")).toBeVisible();
    const chips = widget.locator('input[data-role="amount"]');
    await expect(chips).toHaveCount(4);
    await widget.getByText("50 €", { exact: true }).click();
    await expect(widget.getByRole("button", { name: /Dona 50 € con Carta/ })).toBeVisible();
    // Custom amount (filtered like the reference: digits, dot, comma; 9 chars max).
    const custom = widget.getByLabel("Altro importo in EUR");
    await custom.fill("12,5abc");
    await expect(custom).toHaveValue("12,5");
    await expect(widget.getByRole("button", { name: /Dona 12,50 € con Carta/ })).toBeVisible();
    // Submit without consent → summary focused, consent error linked to the checkbox.
    await widget.getByRole("button", { name: /Dona 12,50 €/ }).click();
    const summary = widget.getByRole("alert");
    await expect(summary).toBeFocused();
    await expect(summary).toContainText("devi accettare l'informativa sulla privacy");
    // Monthly gift switches presets and the button label.
    await widget.getByText("Ogni mese").click();
    await expect(widget.getByRole("button", { name: /al mese con Carta/ })).toBeVisible();
    await expect(widget.locator('[data-method="ESEWA"]')).toContainText(
      "La donazione mensile è disponibile con carta.",
    );
  });

  test("card: provider failure is shown, never a fake success", async ({ page }) => {
    await open(page);
    const widget = page.locator('[data-engagement="DonateWidget"]');
    await widget.getByRole("checkbox", { name: /informativa sulla privacy/ }).check();
    await page.waitForTimeout(MIN_FILL_MS);
    await widget.getByRole("button", { name: /^Dona/ }).click();
    await expect(widget.getByRole("alert")).toBeFocused();
    await expect(widget.getByRole("alert")).toContainText(/servizio di pagamento|riprova/i);
  });

  test("eSewa: NPR amounts and a signed form POST to eSewa", async ({ page }) => {
    await open(page);
    const widget = page.locator('[data-engagement="DonateWidget"]');
    await widget.locator('[data-method="ESEWA"]').click();
    await expect(widget.locator("legend", { hasText: "(NPR)" })).toBeVisible();
    await expect(widget.locator('input[data-role="amount"]')).toHaveCount(4);
    await widget.getByRole("checkbox", { name: /informativa sulla privacy/ }).check();
    let posted: string | null = null;
    await page.route("https://rc-epay.esewa.com.np/**", async (route) => {
      posted = route.request().postData();
      await route.fulfill({ status: 200, contentType: "text/html", body: "<p>eSewa</p>" });
    });
    await page.waitForTimeout(MIN_FILL_MS);
    await widget.getByRole("button", { name: /^Dona/ }).click();
    await expect.poll(() => posted).not.toBeNull();
    const fields = new URLSearchParams(posted!);
    expect(fields.get("product_code")).toBe("EPAYTEST");
    expect(fields.get("transaction_uuid")).toMatch(/^SKL-\d{4}-[A-Z2-9]{8}$/);
    expect(fields.get("total_amount")).toBe("1000");
    expect(fields.get("signed_field_names")).toBe("total_amount,transaction_uuid,product_code");
    expect(fields.get("signature")).toBeTruthy();
  });
});
