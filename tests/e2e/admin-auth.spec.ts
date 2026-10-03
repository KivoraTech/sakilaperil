import { expect, test, type Page } from "@playwright/test";

const ADMIN_EMAIL = process.env.SEED_ADMIN_EMAIL ?? "admin@sakila.local";
const PASSWORD = process.env.SEED_ADMIN_PASSWORD ?? "ChangeMe-Sakila-2026!";

async function login(page: Page, email: string, password: string) {
  await page.goto("/admin/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
}

test.describe("admin authentication", () => {
  test("anonymous users are redirected to the login page", async ({ page }) => {
    await page.goto("/admin/pages");
    await expect(page).toHaveURL(/\/admin\/login\?next=%2Fadmin%2Fpages/);
  });

  test("wrong credentials show a generic error", async ({ page }) => {
    // Unknown, unique account on purpose: never lock the real admin, and never trip the per-email
    // login throttle when E2E runs repeatedly against the same database.
    await login(page, `nobody-${Date.now()}@sakila.local`, "definitely-wrong-password");
    await expect(page.locator("#login-error")).toContainText("Invalid email or password");
    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test("admin can sign in, see the shell and sign out", async ({ page, isMobile }) => {
    await login(page, ADMIN_EMAIL, PASSWORD);
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole("heading", { level: 1 })).toContainText("Namaste");
    if (isMobile) {
      await page.getByRole("button", { name: "Open navigation" }).click();
    }
    const nav = page.getByRole("navigation", { name: "Admin" }).last();
    for (const label of ["Pages", "News & stories", "Settings", "Users", "Audit log"]) {
      await expect(nav.getByRole("link", { name: label })).toBeVisible();
    }
    await nav.getByRole("link", { name: "Settings" }).click();
    await expect(page.getByRole("heading", { level: 1, name: "Settings" })).toBeVisible();

    await page.getByRole("button", { name: /Account menu/ }).click();
    await page.getByTestId("logout").click();
    await expect(page).toHaveURL(/\/admin\/login/);
    await page.goto("/admin");
    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test("authors cannot open admin-only sections", async ({ page }) => {
    await login(page, "author@sakila.local", PASSWORD);
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto("/admin/users");
    await expect(page).toHaveURL(/\/admin\/access-denied/);
    await page.goto("/admin/settings");
    await expect(page).toHaveURL(/\/admin\/access-denied/);
  });
});
