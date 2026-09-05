import { expect, test } from "@playwright/test";

test("login page renders the authentication form", async ({ page }) => {
  await page.goto("/login");

  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  await expect(page.getByLabel(/NIDN|email/i)).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
  await expect(page.getByRole("button", { name: /masuk/i })).toBeVisible();
});

test("responses include baseline browser security headers", async ({ request }) => {
  const response = await request.get("/login");

  expect(response.ok()).toBeTruthy();
  expect(response.headers()["x-content-type-options"]).toBe("nosniff");
  expect(response.headers()["x-frame-options"]).toBe("SAMEORIGIN");
  expect(response.headers()["referrer-policy"]).toBe("strict-origin-when-cross-origin");
  expect(response.headers()["permissions-policy"]).toContain("camera=()");
});