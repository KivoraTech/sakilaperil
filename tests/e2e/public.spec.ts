import { expect, test } from "@playwright/test";

test.describe("public site", () => {
  test("/ redirects to the Italian home page", async ({ page }) => {
    const res = await page.goto("/");
    expect(res?.ok()).toBe(true);
    await expect(page).toHaveURL(/\/it$/);
    await expect(page.locator("html")).toHaveAttribute("lang", "it");
    await expect(page.getByRole("heading", { level: 2 }).first()).toBeVisible();
  });

  test("English home page renders with lang=en", async ({ page }) => {
    await page.goto("/en");
    await expect(page.locator("html")).toHaveAttribute("lang", "en");
    await expect(page.getByText("Together, we open doors for").first()).toBeVisible();
  });

  test("CMS page by localized slug", async ({ page }) => {
    await page.goto("/it/chi-siamo");
    await expect(page.locator("html")).toHaveAttribute("lang", "it");
    const res = await page.goto("/en/about-us");
    expect(res?.status()).toBe(200);
  });

  test("unknown page returns 404", async ({ page }) => {
    const res = await page.goto("/it/questa-pagina-non-esiste");
    expect(res?.status()).toBe(404);
  });

  test("admin is not indexable", async ({ request }) => {
    const robots = await request.get("/robots.txt");
    expect(await robots.text()).toContain("Disallow: /admin");
  });
});
