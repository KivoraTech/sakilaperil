import { expect, test, type Page } from "@playwright/test";

/*
 * Page builder end-to-end: create → add hero → save draft → preview (secure, bannered, noindex)
 * → publish → visible publicly → unpublish/delete. Plus role checks for AUTHOR.
 * Uses the seeded accounts (see .env.example / README).
 */

const ADMIN_EMAIL = process.env.SEED_ADMIN_EMAIL ?? "admin@sakila.local";
const PASSWORD = process.env.SEED_ADMIN_PASSWORD ?? "ChangeMe-Sakila-2026!";
const DEMO_PASSWORD = process.env.SEED_DEMO_PASSWORD ?? PASSWORD;

test.describe.configure({ mode: "serial" });
test.setTimeout(180_000);
// First requests compile routes on a dev server; allow generous assertion timeouts.
test.use({ actionTimeout: 20_000 });
expect.configure({ timeout: 20_000 });

async function login(page: Page, email: string, password: string) {
  await page.goto("/admin/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin$/, { timeout: 30_000 });
}

test("editor flow: create page, add hero, save, preview, publish, visible publicly", async ({
  page,
  context,
  browser,
  isMobile,
}) => {
  const suffix = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
  const title = `Pagina di prova ${suffix}`;
  const slug = `pagina-di-prova-${suffix}`;
  const heading = `Titolo hero ${suffix}`;

  await login(page, ADMIN_EMAIL, PASSWORD);
  await page.goto("/admin/pages");
  await expect(page.getByRole("heading", { level: 1, name: "Pages" })).toBeVisible();

  // Create
  await page.getByRole("button", { name: "New page" }).click();
  const dialog = page.getByRole("dialog", { name: "New page" });
  await dialog.getByLabel("Title (Italian)").fill(title);
  await expect(dialog.getByLabel("Address (Italian)")).toHaveValue(slug);
  await dialog.getByRole("button", { name: "Create page" }).click();
  await expect(page).toHaveURL(/\/admin\/pages\/[a-z0-9]+$/);
  await expect(page.getByText("This page has no sections yet.")).toBeVisible();
  const editorUrl = page.url();

  // Not public yet
  const anon = await browser.newContext();
  const anonPage = await anon.newPage();
  const before = await anonPage.goto(`/it/${slug}`);
  expect(before?.status()).toBe(404);

  // Add a hero block
  await page.getByRole("button", { name: "Add the first section" }).click();
  await page.getByRole("dialog", { name: "Add a section" }).getByRole("button", { name: /^Hero/ }).click();
  // Wide screens edit inline next to the live preview; small screens use a side sheet.
  const sheet = page.getByTestId("block-editor");
  await expect(sheet).toBeVisible();
  await expect(sheet).toContainText("Edit Hero");
  await sheet.getByLabel("Heading", { exact: true }).fill(heading);
  // Applying without an image shows the error (hero requires an image).
  await sheet.getByRole("button", { name: "Apply changes" }).click();
  await expect(sheet.getByText("Choose an image").first()).toBeVisible();
  await sheet.getByRole("button", { name: "Choose image" }).click();
  const picker = page.getByRole("dialog", { name: "Choose an image" });
  // Photos without a description ask for one first; pick one that already has it.
  await picker
    .getByRole("option")
    .filter({ hasNotText: "Needs description" })
    .first()
    .getByRole("button")
    .click();
  await sheet.getByRole("button", { name: "Apply changes" }).click();
  await expect(sheet).toBeHidden();

  const section = page.getByTestId("builder-section");
  await expect(section).toHaveCount(1);
  await expect(section.first()).toContainText(heading);
  await expect(page.getByText("Unsaved changes")).toBeVisible();

  // Save draft
  await page.getByRole("button", { name: "Save draft" }).click();
  await expect(page.getByText("All changes saved")).toBeVisible();
  await expect(page.getByText("Draft — not live")).toBeVisible();

  // Preview in a new tab: bannered, noindex, draft content
  const popupPromise = context.waitForEvent("page");
  if (isMobile) await page.getByRole("button", { name: "Preview", exact: true }).click();
  else await page.getByRole("button", { name: "Open in new tab" }).click();
  const preview = await popupPromise;
  await preview.waitForURL(/\/preview\/it\/pages\//);
  await expect(preview.getByTestId("preview-banner")).toContainText("ANTEPRIMA / PREVIEW — NOT LIVE");
  await expect(preview.getByText(heading).first()).toBeVisible();
  await expect(preview.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/);
  const previewUrl = preview.url();
  await preview.close();

  // The preview URL is not reachable without a session
  await anonPage.goto(previewUrl);
  await expect(anonPage).toHaveURL(/\/admin\/login/);

  // Publish
  await page.getByRole("button", { name: "Publish" }).click();
  await expect(page.getByText(/Published Italiano version/)).toBeVisible();
  await expect(page.getByText("Live", { exact: true })).toBeVisible();

  // Visible publicly
  const after = await anonPage.goto(`/it/${slug}`);
  expect(after?.status()).toBe(200);
  await expect(anonPage.getByText(heading).first()).toBeVisible();

  // Unpublish → gone again
  await page.goto(editorUrl);
  await page.getByRole("button", { name: "More page actions" }).click();
  await page.getByRole("menuitem", { name: "Unpublish" }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Unpublish" }).click();
  await expect(page.getByText("Draft — not live")).toBeVisible();
  const gone = await anonPage.goto(`/it/${slug}`);
  expect(gone?.status()).toBe(404);

  // Clean up: delete
  await page.getByRole("button", { name: "More page actions" }).click();
  await page.getByRole("menuitem", { name: "Delete page" }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Delete page" }).click();
  await expect(page).toHaveURL(/\/admin\/pages$/);
  await anon.close();
});

test("live preview shows unsaved edits side by side without saving them", async ({ page, isMobile }) => {
  test.skip(isMobile, "The side-by-side preview is for wide screens");
  const suffix = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
  const title = `Anteprima dal vivo ${suffix}`;
  const typed = `Titolo in diretta ${suffix}`;

  await login(page, ADMIN_EMAIL, PASSWORD);
  await page.goto("/admin/pages");
  await page.getByRole("button", { name: "New page" }).click();
  const dialog = page.getByRole("dialog", { name: "New page" });
  await dialog.getByLabel("Title (Italian)").fill(title);
  await dialog.getByRole("button", { name: "Create page" }).click();
  await expect(page).toHaveURL(/\/admin\/pages\/[a-z0-9]+$/);

  const frame = page.frameLocator('[data-testid="live-preview-frame"]');
  await expect(page.getByRole("button", { name: "Live preview" })).toHaveAttribute("aria-pressed", "true");

  // Add a Call to action and type a heading: the preview follows before "Apply" or "Save".
  await page.getByRole("button", { name: "Add the first section" }).click();
  await page
    .getByRole("dialog", { name: "Add a section" })
    .getByRole("button", { name: /^Call to action/ })
    .click();
  const editor = page.getByTestId("block-editor");
  await expect(editor).toBeVisible();
  await editor.getByLabel("Heading", { exact: true }).fill(typed);
  await expect(frame.getByText(typed).first()).toBeVisible();
  await expect(page.getByText("Unsaved changes")).toBeVisible();

  // Cancel reverts the preview to the section's applied content.
  await editor.getByRole("button", { name: "Cancel" }).click();
  await expect(frame.getByText(typed)).toHaveCount(0);

  // Edit again and apply: still unsaved, still in the preview.
  await page
    .getByTestId("builder-section")
    .first()
    .getByRole("button", { name: /^Edit section 1/ })
    .click();
  await page.getByTestId("block-editor").getByLabel("Heading", { exact: true }).fill(typed);
  await page.getByTestId("block-editor").getByRole("button", { name: "Apply changes" }).click();
  await expect(frame.getByText(typed).first()).toBeVisible();

  // The SAVED draft (what the normal preview shows) is unchanged.
  const pageId = page.url().split("/admin/pages/")[1]!.split("?")[0];
  const saved = await page.context().newPage();
  await saved.goto(`/preview/it/pages/${pageId}`);
  await expect(saved.getByTestId("preview-banner")).toBeVisible();
  await expect(saved.getByText(typed)).toHaveCount(0);
  await saved.close();

  // Clean up.
  await page.getByRole("button", { name: "More page actions" }).click();
  await page.getByRole("menuitem", { name: "Delete page" }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Delete page" }).click();
  await expect(page).toHaveURL(/\/admin\/pages$/);
});

test("author can edit drafts but cannot publish or open Users", async ({ page }) => {
  await login(page, "author@sakila.local", DEMO_PASSWORD);
  await page.goto("/admin/users");
  await expect(page).toHaveURL(/\/admin\/access-denied/);

  await page.goto("/admin/pages");
  await expect(page.getByRole("button", { name: "New page" })).toHaveCount(0);
  await page
    .getByRole("link", { name: /^Edit / })
    .first()
    .click();
  await expect(page.getByRole("button", { name: "Save draft" })).toBeVisible();
  await expect(page.getByRole("button", { name: /Publish/ })).toHaveCount(0);
  await expect(page.getByText("An editor will publish your changes.")).toBeVisible();
});
