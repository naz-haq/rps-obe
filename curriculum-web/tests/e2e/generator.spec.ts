/**
 * Fixture-backed UI regressions: real standalone Next pages, cookies, SSR,
 * Server Actions and refresh; synthetic HTTP API on 127.0.0.1:8111.
 * No page.route mocks, app test routes, real database or AI. These tests do NOT
 * prove Laravel authorization/validation, database locking, approval audit
 * persistence, provider behavior or real API compatibility; run the backend
 * integration suite separately for those contracts.
 *
 * Run from curriculum-web after rebuilding current source with npm run build:
 * npm run test:e2e -- tests/e2e/generator.spec.ts
 * Playwright starts/stops both servers; ports 3011 and 8111 must be unused.
 * Do not skip/soften failures for pinned controls, mobile overflow or a stale
 * cookie redirect loop: those are intentional production UI regressions.
 */
import { randomUUID } from "node:crypto";
import { expect, test as base, type Locator, type Page } from "@playwright/test";
import type { GenerateSession } from "../../src/lib/api";
import type { Draf } from "../../src/app/generator/[id]/draft";

const ORIGIN = "http://127.0.0.1:3011";
const API = "http://127.0.0.1:8111/api/v1";
const SESSION = { draft: 1, accepted: 2, edited: 3, pinned: 4, committed: 5, approved: 6 } as const;
type Snapshot = Omit<GenerateSession, "draf"> & { draf: Draf };
type OutcomeStage = "cpmk" | "sub_cpmk";

const test = base.extend<{ token: string }>({
  token: [async ({ context }, use) => {
    // Fresh context AND token on every test/retry/worker; no shared reset call.
    const token = `generator-e2e-${randomUUID()}`;
    await context.addCookies([{ name: "rps_token", value: token, url: ORIGIN, httpOnly: true, sameSite: "Lax" }]);
    await use(token);
  }, { auto: true }],
});

async function snapshot(page: Page, token: string, id: number): Promise<Snapshot> {
  const response = await page.request.get(`${API}/generate-sessions/${id}`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  expect(response.status()).toBe(200);
  return (await response.json()).data as Snapshot;
}

async function openSession(page: Page, id: number) {
  const response = await page.goto(`/generator/${id}`);
  expect(response?.status()).toBe(200);
  await expect(page).toHaveURL(`${ORIGIN}/generator/${id}`);
  await expect(page.getByRole("heading", { level: 1, name: "Farmakologi Fixture", exact: true })).toBeVisible();
}

async function selectStage(page: Page, label: string) {
  // Stage tabs contain number/checkmark, count and status as well as the label.
  await page.getByRole("button", { name: new RegExp(`^(?:[1-4]|✓)\\s*${label}(?:\\s|$)`) }).click();
}

function row(page: Page, code: string) {
  // A detail card in the main rincian view, identified by its kode badge.
  return page.getByRole("listitem").filter({ has: page.getByText(code, { exact: true }) });
}

async function expectEditableDetail(page: Page, prefix: "CPMK" | "Sub-CPMK") {
  for (const n of [1, 2, 3]) {
    const r = row(page, `${prefix}-${n}`);
    await expect(r.getByRole("button", { name: "✨ Perbaiki", exact: true })).toBeEnabled();
    await expect(r.getByRole("button", { name: "Sematkan", exact: true })).toBeEnabled();
  }
  await expect(page.getByRole("button", { name: "Regenerasi AI (semua)", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Regenerasi AI (semua)", exact: true })).toBeEnabled();
  await expect(page.getByRole("button", { name: "Edit manual", exact: true })).toBeEnabled();
}

test("accepted and edited CPMK/Sub-CPMK expose per-item AI, pin and whole-stage regeneration", async ({ page }) => {
  for (const id of [SESSION.accepted, SESSION.edited]) {
    await openSession(page, id);
    for (const label of ["CPMK", "Sub-CPMK"] as const) {
      await selectStage(page, label);
      await expectEditableDetail(page, label);
    }
  }
});

test("the main detail view retains mappings, indicators, weekly methods and rubric descriptors", async ({ page }) => {
  await openSession(page, SESSION.edited);
  await selectStage(page, "CPMK");
  const c1 = row(page, "CPMK-1");
  await expect(c1.getByText("CPL-1", { exact: true })).toBeVisible();
  await expect(c1.getByText("Menganalisis mekanisme obat kelompok 1.", { exact: true })).toBeVisible();

  await selectStage(page, "Sub-CPMK");
  const s1 = row(page, "Sub-CPMK-1");
  await expect(s1.getByText("← CPMK-1", { exact: true })).toBeVisible();
  await expect(s1.getByText("Mengidentifikasi tiga mekanisme kelompok 1.", { exact: true })).toBeVisible();
  await expect(s1.getByText("Menjelaskan dua interaksi kelompok 1.", { exact: true })).toBeVisible();

  await selectStage(page, "Rencana Mingguan");
  const weekly = page.locator("table").filter({ has: page.getByRole("columnheader", { name: /Materi Pembelajaran/ }) });
  const firstWeek = weekly.getByRole("row").filter({ has: page.getByRole("cell", { name: "1", exact: true }) });
  await expect(firstWeek.getByText("Menjelaskan mekanisme pada kasus minggu 1.", { exact: true })).toBeVisible();
  await expect(firstWeek.getByText("Metode: Case-based learning dan diskusi kelompok", { exact: true })).toBeVisible();
  await expect(firstWeek.getByText("Forum LMS dan kuis formatif", { exact: true })).toBeVisible();
  await expect(firstWeek.getByText("Penugasan: Menyusun peta konsep secara mandiri", { exact: true })).toBeVisible();
  await expect(firstWeek.getByText("TM: 2 × 50; PT: 2 × 60; BM: 2 × 60 menit", { exact: true })).toBeVisible();
  await expect(weekly.getByRole("row")).toHaveCount(17);
  // UTS/UAS rows keep every KPT column visible instead of collapsing into a band.
  await expect(weekly.getByText("Evaluasi Tengah Semester (UTS)", { exact: true })).toBeVisible();
  await expect(weekly.getByText("Evaluasi Akhir Semester (UAS)", { exact: true })).toBeVisible();

  await selectStage(page, "Penilaian");
  const rubric = page.getByRole("listitem").filter({ has: page.getByText("Analisis kasus 1", { exact: true }) });
  await expect(rubric.getByRole("columnheader", { name: "Sangat baik", exact: true })).toBeVisible();
  await expect(rubric.getByRole("cell", { name: "Ketepatan mekanisme", exact: true })).toBeVisible();
  await expect(rubric.getByRole("cell", { name: "60%", exact: true })).toBeVisible();
  await expect(rubric.getByRole("cell", { name: "Semua mekanisme tepat dan beralasan", exact: true })).toBeVisible();
  await expect(rubric.getByRole("cell", { name: "Bukti relevan dan kritis", exact: true })).toBeVisible();
  await expect(page.getByText("Total bobot: 100%", { exact: true })).toBeVisible();
});

test("pinned stages are read-only; unpin restores actions and pin locks them again", async ({ page, token }) => {
  await openSession(page, SESSION.pinned);
  for (const [stage, label] of [["cpmk", "CPMK"], ["sub_cpmk", "Sub-CPMK"]] as const) {
    await selectStage(page, label);
    const before = await snapshot(page, token, SESSION.pinned);
    await expect(page.getByText("Tahap disematkan. Lepas sematan tahap untuk regenerasi atau penyuntingan.")).toBeVisible();
    await expect(page.getByRole("button", { name: /Regenerasi AI/ })).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Edit manual", exact: true })).toBeDisabled();
    // A pinned stage hides every per-item control in the detail view.
    await expect(page.getByRole("button", { name: "✨ Perbaiki", exact: true })).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Sematkan", exact: true })).toHaveCount(0);

    await page.getByRole("button", { name: "Lepas sematan tahap", exact: true }).click();
    await expectEditableDetail(page, label);
    const unpinned = await snapshot(page, token, SESSION.pinned);
    expect(unpinned.status_bagian?.[stage]).toBe("accepted");
    expect(unpinned.revisi).toBe(before.revisi! + 1);
    expect(unpinned.draf).toEqual(before.draf);

    await page.getByRole("button", { name: "Sematkan tahap", exact: true }).click();
    await expect(page.getByRole("button", { name: "Lepas sematan tahap", exact: true })).toBeVisible();
    await expect(page.getByRole("button", { name: "Edit manual", exact: true })).toBeDisabled();
    expect((await snapshot(page, token, SESSION.pinned)).status_bagian?.[stage]).toBe("pinned");
  }
});

test("per-item pin/unpin affects only the intended row and its draft flag", async ({ page, token }) => {
  await openSession(page, SESSION.draft);
  for (const label of ["CPMK", "Sub-CPMK"] as const) {
    await selectStage(page, label);
    const original = await snapshot(page, token, SESSION.draft);
    const r1 = row(page, `${label}-1`);
    await r1.getByRole("button", { name: "Sematkan", exact: true }).click();
    await expect(r1.getByRole("button", { name: "📌 Tersemat", exact: true })).toBeVisible();
    await expect(r1.getByRole("button", { name: "✨ Perbaiki", exact: true })).toBeDisabled();
    await expect(row(page, `${label}-2`).getByRole("button", { name: "Sematkan", exact: true })).toBeEnabled();
    const pinned = await snapshot(page, token, SESSION.draft);
    const expected = structuredClone(original.draf);
    const expectedItems = label === "CPMK" ? expected.cpmk!.cpmk! : expected.sub_cpmk!.sub_cpmk!;
    expectedItems[0]._pin = true;
    expect(pinned.draf).toEqual(expected);

    await r1.getByRole("button", { name: "📌 Tersemat", exact: true }).click();
    await expect(r1.getByRole("button", { name: "✨ Perbaiki", exact: true })).toBeEnabled();
    expect((await snapshot(page, token, SESSION.draft)).draf).toEqual(original.draf);
    for (const n of [1, 2, 3]) await expect(row(page, `${label}-${n}`).getByRole("button", { name: "✨ Perbaiki", exact: true })).toBeEnabled();
  }
});

test("candidate preview never overwrites a draft; applying changes only the targeted outcome", async ({ page, token }) => {
  // Distinct sessions keep the second scenario independent of downstream flags.
  for (const [stage, label, id] of [["cpmk", "CPMK", SESSION.accepted], ["sub_cpmk", "Sub-CPMK", SESSION.edited]] as const) {
    await openSession(page, id);
    await selectStage(page, label);
    const before = await snapshot(page, token, id);
    const originalItems = outcomeItems(before.draf, stage);
    // Deliberately target the second row: touching the wrong item must not pass.
    const original = originalItems[1];
    const replacement = `${original.deskripsi} (usulan deterministik).`;
    const r2 = row(page, `${label}-2`);
    await r2.getByRole("button", { name: "✨ Perbaiki", exact: true }).click();
    await r2.getByRole("textbox").fill("Perjelas rumusan tanpa mengubah identitas.");
    await r2.getByRole("button", { name: "Buat usulan", exact: true }).click();
    await expect(r2.getByText("Pratinjau perubahan", { exact: true })).toBeVisible();
    await expect(r2.getByText(replacement, { exact: true })).toBeVisible();
    await expect(r2.getByText(/fixture-no-ai/)).toBeVisible();
    await expect(r2.locator(":scope > p")).toHaveText(original.deskripsi);
    expect(await snapshot(page, token, id)).toEqual(before);
    await r2.getByRole("button", { name: "Terapkan", exact: true }).click();
    await expect(r2.locator(":scope > p")).toHaveText(replacement);
    await expect(page.getByText("Pratinjau perubahan", { exact: true })).toHaveCount(0);
    const after = await snapshot(page, token, id);
    const updated = outcomeItems(after.draf, stage);
    expect(after.revisi).toBe(before.revisi! + 1);
    expect(updated[1]).toEqual({ ...original, deskripsi: replacement });
    expect([updated[0], updated[2]]).toEqual([originalItems[0], originalItems[2]]);
    // Assert the ENTIRE draft, including the precise downstream review flags;
    // no other content, IDs, relationships, indicators or rubrics may change.
    const expected = structuredClone(before.draf);
    outcomeItems(expected, stage)[1].deskripsi = replacement;
    if (stage === "cpmk") expected.sub_cpmk!.sub_cpmk![1]._needs_review = true;
    for (const child of [...expected.mingguan!.minggu!, ...expected.penilaian!.komponen!]) {
      if (child.sub_cpmk_kode === "Sub-CPMK-2") child._needs_review = true;
    }
    expect(after.draf).toEqual(expected);
    for (const n of [1, 3]) await expect(row(page, `${label}-${n}`).locator(":scope > p")).toHaveText(originalItems[n - 1].deskripsi);
    await page.reload();
    await selectStage(page, label);
    await expect(row(page, `${label}-2`).locator(":scope > p")).toHaveText(replacement);
  }
});

function outcomeItems(draf: Draf, stage: OutcomeStage) {
  return stage === "cpmk" ? draf.cpmk!.cpmk! : draf.sub_cpmk!.sub_cpmk!;
}

test("committed and approved sessions have no checkboxes; only never-approved RPS can reopen", async ({ page, token }) => {
  for (const id of [SESSION.approved, SESSION.committed]) {
    await openSession(page, id);
    for (const label of ["CPMK", "Sub-CPMK", "Rencana Mingguan", "Penilaian"]) {
      await selectStage(page, label);
      await expect(page.getByRole("checkbox")).toHaveCount(0);
      await expect(page.getByRole("button", { name: /Regenerasi AI|Edit manual|Sematkan tahap/ })).toHaveCount(0);
      await expect(page.getByRole("button", { name: "✨ Perbaiki", exact: true })).toHaveCount(0);
    }
    if (id === SESSION.approved) {
      await expect(page.getByRole("button", { name: "Kembalikan ke draf", exact: true })).toHaveCount(0);
      await expect(page.getByText("RPS telah disetujui prodi dan tidak dapat dikembalikan ke draf.")).toBeVisible();
      continue;
    }
    const before = await snapshot(page, token, id);
    await page.getByRole("button", { name: "Kembalikan ke draf", exact: true }).click();
    const dialog = page.getByRole("dialog", { name: "Kembalikan RPS ke draf?", exact: true });
    await expect(dialog).toBeVisible();
    await dialog.getByPlaceholder("Alasan perbaikan", { exact: true }).fill("Perjelas indikator pembelajaran.");
    await dialog.getByRole("button", { name: "Kembalikan ke draf", exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.getByRole("status").filter({ hasText: "Draf dibuka kembali." })).toBeVisible();
    for (const label of ["CPMK", "Sub-CPMK"] as const) {
      await selectStage(page, label);
      await expectEditableDetail(page, label);
    }
    const after = await snapshot(page, token, id);
    expect(after.status).toBe("berjalan");
    expect(after.rps_status).toBe("draft");
    expect(after.can_reopen).toBe(false);
    expect(after.rps_version_id).toBe(before.rps_version_id);
    expect(after.draf).toEqual(before.draf);
    expect(after.revisi).toBe(before.revisi! + 1);
  }
});

async function expectWithinMobileViewport(page: Page, control: Locator) {
  await expect(control).toBeVisible();
  await control.scrollIntoViewIfNeeded();
  await expect(control).toBeInViewport();
  const box = await control.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.x).toBeGreaterThanOrEqual(0);
  expect(box!.x + box!.width).toBeLessThanOrEqual(page.viewportSize()!.width + 1);
}

async function expectNoDocumentOverflow(page: Page) {
  // Closed rich-detail tables are not involved here. When testing expanded
  // weekly/rubric tables, their local overflow-x-auto wrapper may scroll;
  // document-level overflow is never acceptable.
  const widths = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
    body: document.body.scrollWidth,
  }));
  expect(widths.document).toBeLessThanOrEqual(widths.viewport + 1);
  expect(widths.body).toBeLessThanOrEqual(widths.viewport + 1);
}

test("375px layout keeps per-item pin and AI controls visible without horizontal overflow", async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await openSession(page, SESSION.accepted);
  for (const label of ["CPMK", "Sub-CPMK"] as const) {
    await selectStage(page, label);
    const r1 = row(page, `${label}-1`);
    for (const control of [
      r1.getByRole("button", { name: "Sematkan", exact: true }),
      r1.getByRole("button", { name: "✨ Perbaiki", exact: true }),
      page.getByRole("button", { name: "Regenerasi AI (semua)", exact: true }),
    ]) {
      await expect(control).toBeEnabled();
      await expectWithinMobileViewport(page, control);
    }
    await expectNoDocumentOverflow(page);
    await r1.getByRole("button", { name: "✨ Perbaiki", exact: true }).click();
    await expectWithinMobileViewport(page, r1.getByRole("textbox"));
    await r1.getByRole("button", { name: "Buat usulan", exact: true }).click();
    await expectWithinMobileViewport(page, r1.getByRole("button", { name: "Terapkan", exact: true }));
    await expectNoDocumentOverflow(page);
    await r1.getByRole("button", { name: "Tolak", exact: true }).click();
    await r1.getByRole("button", { name: "✨ Perbaiki", exact: true }).click();
  }
});

test("a stale cookie reaches a usable login form without a dashboard redirect loop", async ({ page, context, token }) => {
  await context.addCookies([{ name: "rps_token", value: `generator-e2e-stale-${token}`, url: ORIGIN, httpOnly: true, sameSite: "Lax" }]);
  let navigations = 0;
  page.on("request", (request) => {
    if (request.isNavigationRequest() && request.frame() === page.mainFrame()) navigations++;
  });
  await page.goto("/generator/1");
  await expect(page).toHaveURL(`${ORIGIN}/login`);
  await expect(page.getByLabel(/NIDN|email/i)).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
  await expect(page.getByRole("button", { name: /masuk/i })).toBeEnabled();
  await page.reload();
  await expect(page).toHaveURL(`${ORIGIN}/login`);
  await expect(page.getByLabel(/NIDN|email/i)).toBeVisible();
  expect(navigations).toBeLessThanOrEqual(4);
});