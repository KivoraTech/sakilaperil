import { expect, test, type Page } from "@playwright/test";

/**
 * CONTENT track E2E: login → create news with rich text + uploaded image → save draft →
 * not public → publish → visible on /it/news. Plus author permissions.
 * Needs a seeded DB (admin@ / author@sakila.local) and a running server (see playwright.config.ts).
 */
const ADMIN_EMAIL = process.env.SEED_ADMIN_EMAIL ?? "admin@sakila.local";
const PASSWORD = process.env.SEED_ADMIN_PASSWORD ?? "ChangeMe-Sakila-2026!";

// 1×1 PNG — decodable by sharp, so it exercises the real upload pipeline.
const PNG = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",
  "base64",
);

const PDF = "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<< /Root 1 0 R >>\n%%EOF\n";

async function login(page: Page, email: string, password = PASSWORD) {
  await page.goto("/admin/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin$/);
}

test.describe("admin content", () => {
  test("news: draft is private, publish makes it public", async ({ page }, info) => {
    const stamp = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}-${info.project.name}`;
    const title = `Notizia E2E ${stamp}`;
    const slug = `notizia-e2e-${stamp}`.toLowerCase();

    await login(page, ADMIN_EMAIL);
    await page.goto("/admin/news/new");
    await page.getByLabel("Title *", { exact: true }).fill(title);
    await expect(page.getByLabel("Slug (URL)")).toHaveValue(slug);
    await page.getByLabel("Excerpt").fill("Un aggiornamento dal campo.");

    // Rich text: bold text via the toolbar.
    const editor = page.getByRole("textbox", { name: "Body" });
    await editor.click();
    await page.getByRole("button", { name: "Bold" }).click();
    await editor.pressSequentially("Testo in grassetto");
    await page.getByRole("button", { name: "Bold" }).click();
    await editor.pressSequentially(" e testo normale.");

    // Cover image: upload through the media picker (alt text is asked for).
    await page.getByRole("button", { name: "Choose an image…" }).click();
    const picker = page.getByTestId("media-picker");
    await picker.getByRole("tab", { name: "Upload" }).click();
    await picker
      .getByLabel("Upload files")
      .setInputFiles({ name: "e2e-cover.png", mimeType: "image/png", buffer: PNG });
    await picker.getByLabel("Alt text (Italian) *").fill("Bambini in una scuola del Nepal");
    await picker.getByRole("button", { name: "Save alt text & select" }).click();
    await expect(picker).toBeHidden();
    await expect(page.getByText("e2e-cover.png")).toBeVisible();

    // Save as draft → redirected to the edit page.
    await page.getByTestId("save").click();
    await expect(page.getByText("Draft saved")).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/news\/[a-z0-9]+$/);

    // Draft is not public.
    const detail = await page.request.get(`/it/news/${slug}`);
    expect(detail.status()).toBe(404);
    await page.goto("/it/news");
    await expect(page.getByText(title)).toHaveCount(0);

    // Publish.
    await page.goBack();
    await expect(page.getByTestId("publish")).toBeVisible();
    await page.getByTestId("publish").click();
    await expect(page.getByText("Saved — live on the site")).toBeVisible();

    await page.goto("/it/news");
    await expect(page.getByText(title).first()).toBeVisible();
    await page.goto(`/it/news/${slug}`);
    await expect(page.getByRole("heading", { level: 1 })).toContainText(title);
    await expect(page.locator("strong", { hasText: "Testo in grassetto" })).toBeVisible();
  });

  test("editors publish events, albums (photo + video) and reports (PDF)", async ({ page }, info) => {
    const stamp = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}-${info.project.name}`;
    await login(page, ADMIN_EMAIL);

    // Event
    await page.goto("/admin/events/new");
    await page.getByLabel("Title *", { exact: true }).fill(`Cena E2E ${stamp}`);
    await page.getByLabel("Starts *").fill("2030-05-10T19:30");
    await page.getByLabel("Registration link").fill("https://example.org/register");
    await page.getByTestId("publish").click();
    await expect(page.getByText("Saved — live on the site")).toBeVisible();
    const eventRes = await page.request.get(`/it/events/cena-e2e-${stamp}`.toLowerCase());
    expect(eventRes.status()).toBe(200);

    // Album with an uploaded photo and a YouTube video, then publish.
    await page.goto("/admin/gallery/new");
    await page.getByLabel("Title *", { exact: true }).fill(`Album E2E ${stamp}`);
    await page.getByRole("button", { name: "Add YouTube/Vimeo video" }).click();
    await page.getByLabel("Video link").fill("https://youtu.be/dQw4w9WgXcQ");
    await page.getByRole("button", { name: "Add video" }).click();
    await page.getByRole("button", { name: "Upload photos" }).click();
    await page
      .getByLabel("Upload files")
      .setInputFiles({ name: "album-photo.png", mimeType: "image/png", buffer: PNG });
    await expect(page.getByRole("list", { name: "Album items" }).getByRole("listitem")).toHaveCount(2);
    // Photo has no alt text yet → publishing is blocked with a clear message.
    await page.getByTestId("publish").click();
    await expect(page.getByText(/Add alt text/)).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/gallery\/new$/);
    await page.getByTestId("save").click();
    await expect(page.getByText("Draft saved")).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/gallery\/[a-z0-9]+$/);

    // Report with a PDF.
    await page.goto("/admin/reports/new");
    await page.getByLabel("Title *", { exact: true }).fill(`Bilancio E2E ${stamp}`);
    await page.getByRole("button", { name: "Choose a PDF…" }).click();
    const picker = page.getByTestId("media-picker");
    await picker.getByRole("tab", { name: "Upload" }).click();
    await picker
      .getByLabel("Upload files")
      .setInputFiles({ name: "bilancio.pdf", mimeType: "application/pdf", buffer: Buffer.from(PDF) });
    await picker.getByRole("button", { name: "Select", exact: true }).click();
    await expect(page.getByText("bilancio.pdf")).toBeVisible();
    await page.getByTestId("publish").click();
    await expect(page.getByText("Saved — live on the site")).toBeVisible();
  });

  test("authors can save drafts but not publish, and cannot manage albums", async ({ page }, info) => {
    const stamp = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}-${info.project.name}`;
    await login(page, "author@sakila.local");
    await page.goto("/admin/news/new");
    await page.getByLabel("Title *", { exact: true }).fill(`Bozza autore ${stamp}`);
    await expect(page.getByTestId("publish")).toHaveCount(0);
    await page.getByTestId("save").click();
    await expect(page.getByText("Draft saved")).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/news\/[a-z0-9]+$/);

    await page.goto("/admin/gallery/new");
    await expect(page).toHaveURL(/\/admin\/access-denied/);
  });

  test("media upload API rejects anonymous and disguised files", async ({ page, request, baseURL }) => {
    const anon = await request.post("/api/media", {
      headers: { origin: new URL(baseURL ?? "http://localhost").origin },
      multipart: { file: { name: "x.png", mimeType: "image/png", buffer: PNG } },
    });
    expect([401, 403]).toContain(anon.status());

    await login(page, ADMIN_EMAIL);
    const origin = new URL(page.url()).origin;
    const disguised = await page.request.post("/api/media", {
      headers: { origin },
      multipart: {
        file: {
          name: "evil.png",
          mimeType: "image/png",
          buffer: Buffer.from("<svg onload=alert(1)></svg>"),
        },
      },
    });
    expect(disguised.status()).toBe(415);
  });
});
