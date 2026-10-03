import { expect, test, type Page } from "@playwright/test";

/**
 * Audit regressions (QA-01, QA-07, QA-12): the admin editors and Settings must fit the screen at laptop
 * and phone widths, and an unknown admin address stays inside the admin shell.
 * Needs the seeded DB (admin@sakila.local) and a running server (see playwright.config.ts).
 */
const ADMIN_EMAIL = process.env.SEED_ADMIN_EMAIL ?? "admin@sakila.local";
const PASSWORD = process.env.SEED_ADMIN_PASSWORD ?? "ChangeMe-Sakila-2026!";

async function login(page: Page) {
  await page.goto("/admin/login");
  await page.getByLabel("Email").fill(ADMIN_EMAIL);
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

const overflow = (page: Page) =>
  page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

/** First "edit" address of a list screen (an existing item, so the cover/file rows are rendered). */
async function firstEditHref(page: Page, kind: string): Promise<string | null> {
  await page.goto(`/admin/${kind}`);
  // The list is rendered client-side (Inertia): wait for a row link, not just the "New" button.
  const link = page.locator(`a[href^="/admin/${kind}/"]:not([href$="/new"])`).first();
  await expect(link, `seeded ${kind} item`).toBeVisible();
  return link.getAttribute("href");
}

test.describe("admin screens fit the viewport", () => {
  // One browser is enough: the test sets its own viewport sizes.
  test.skip(({ isMobile }) => isMobile, "viewport sizes are set explicitly");

  test("content editors do not scroll sideways at 1440, 1024 and 390", async ({ page }) => {
    test.setTimeout(120_000); // 12 full page loads
    await login(page);
    const targets: string[] = [];
    for (const kind of ["news", "projects", "events", "gallery"]) {
      const href = await firstEditHref(page, kind);
      expect(href, `seeded ${kind} item`).not.toBeNull();
      targets.push(href!);
    }
    for (const [width, height] of [
      [1440, 900],
      [1024, 800],
      [390, 844],
    ]) {
      await page.setViewportSize({ width, height });
      for (const href of targets) {
        await page.goto(href);
        await expect(page.getByTestId("save")).toBeVisible();
        expect(await overflow(page), `${href} at ${width}px`).toBeLessThanOrEqual(0);
      }
    }
  });

  test("settings do not scroll sideways on a phone", async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 390, height: 844 });
    for (const tab of ["organization", "contact", "donations", "seo", "appearance"]) {
      await page.goto(`/admin/settings?tab=${tab}`);
      await expect(page.getByRole("heading", { level: 1, name: "Settings" })).toBeVisible();
      expect(await overflow(page), `settings tab ${tab}`).toBeLessThanOrEqual(0);
    }
  });

  test("an unknown admin address shows a 404 inside the admin shell", async ({ page }) => {
    await login(page);
    const res = await page.goto("/admin/this-does-not-exist");
    expect(res?.status()).toBe(404);
    await expect(page.getByRole("heading", { level: 1 })).toContainText("no longer exists");
    await expect(page.getByRole("link", { name: "Back to dashboard" })).toBeVisible();
    const deleted = await page.goto("/admin/news/this-item-was-deleted");
    expect(deleted?.status()).toBe(404);
    await expect(page.getByRole("link", { name: "Back to news" })).toBeVisible();
  });
});
