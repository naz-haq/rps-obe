/**
 * Deterministic UI fixture, NOT a Laravel/API integration test or AI emulator.
 * Real Next SSR, auth layout, Server Actions and router.refresh use this server.
 * No database, credentials, outbound fetches, persistence or real AI calls.
 * State is lazy and isolated by the unique Bearer token issued by each test.
 * Session IDs: draft=1, accepted=2, edited=3, pinned=4,
 * committed/review (never approved)=5, committed/approved=6.
 *
 * Resource contract: GET and mutations return {data: resource}; item-candidate
 * returns {data: candidate}, NOT {data: {data: candidate}}. api.ts send() unwraps
 * exactly once. Keep this fixture aligned with GenerateSessionResource and
 * GenerateSessionController; real authorization, transactions, AI grounding,
 * validation and approval audit persistence belong in the backend test suite.
 */
import { createServer } from "node:http";
import { isDeepStrictEqual } from "node:util";

const HOST = "127.0.0.1";
const PORT = 8111;
const PREFIX = "/api/v1";
const DATE = "2026-09-05T00:00:00.000000Z";
const ITEM_KEYS = { cpmk: "cpmk", sub_cpmk: "sub_cpmk", mingguan: "minggu", penilaian: "komponen" };
const states = new Map();
const meta = (id) => ({ _id: String(id).padStart(26, "0"), _pin: false });
const publicItem = (item) => Object.fromEntries(Object.entries(item).filter(([key]) => !key.startsWith("_")));

const user = {
  id: 1, name: "Dosen Fixture", email: "dosen@example.test", nidn: "0000000001",
  jabatan: "Dosen", is_active: true, institusi_id: 1,
  institusi: { id: 1, nama: "Program Studi Fixture", jenis: "prodi" },
  roles: ["dosen"],
  permissions: ["dashboard.view", "generator.view", "generator.create", "generator.edit", "rps.view"],
};
const mk = {
  id: 1, institusi_id: 1, institusi_nama: "Program Studi Fixture", kurikulum_id: 1,
  kode_mk: "FIX101", nama: "Farmakologi Fixture", jenis_mk: "teori", pola: "reguler",
  jumlah_minggu: 16, jumlah_minggu_efektif: 16, jumlah_pertemuan: 16,
  sifat: "wajib", rumpun: "Farmakologi", deskripsi_singkat: "Analisis terapi berbasis bukti untuk pengujian UI.",
  sks_teori: 2, sks_praktik: 0, sks: 2, semester: 3, prodi_kode: "FIX", prasyarat_kode: null,
  estimasi_waktu: { tm_menit: 100, pt_menit: 120, bm_menit: 120, praktik_menit: 0,
    total_menit: 340, jumlah_pertemuan: 16, mode: "sebar", teks: "TM: 2 × 50; PT: 2 × 60; BM: 2 × 60 menit" },
};
const cpl = [1, 2, 3].map((n) => ({
  id: n, institusi_id: 1, kurikulum_id: 1, kode: `CPL-${n}`,
  deskripsi: `Menganalisis bukti farmakologi bidang ${n}.`, aspek: "pengetahuan",
  level_kkni: "6", sumber: "Fixture deterministik",
}));
const taksonomi = [2, 3, 4].map((level) => ({
  id: level, institusi_id: null, domain: "kognitif", kerangka: "bloom_anderson",
  kode: `C${level}`, nama: { 2: "Memahami", 3: "Menerapkan", 4: "Menganalisis" }[level],
  level, deskripsi: "Taksonomi fixture", kata_kerja: ["menjelaskan", "menerapkan", "menganalisis"],
}));
const aturan = ["konversi_sks", "jumlah_minggu", "bobot_teori", "bobot_praktikum"].map((jenis, i) => ({
  id: i + 1, institusi_id: 1, jenis_aturan: jenis,
  nilai: jenis === "jumlah_minggu" ? { jumlah: 16 } : jenis === "konversi_sks"
    ? { tm: 50, pt: 60, bm: 60 } : { komponen: [{ nama: "Tugas", bobot: 100 }] },
  badan_rujukan_id: null, referensi_dokumen_id: null, referensi_halaman: null,
}));

function makeDraft() {
  return {
    cpmk: { cpmk: [1, 2, 3].map((n) => ({
      ...meta(100 + n), kode: `CPMK-${n}`, deskripsi: `Menganalisis mekanisme obat kelompok ${n}.`,
      cpl_kode: [`CPL-${n}`], taksonomi_kode: ["C4"],
    })) },
    sub_cpmk: { sub_cpmk: [1, 2, 3].map((n) => ({
      ...meta(200 + n), kode: `Sub-CPMK-${n}`, cpmk_kode: `CPMK-${n}`,
      deskripsi: `Menjelaskan hubungan dosis dan respons kelompok ${n}.`, taksonomi_kode: ["C2"],
      indikator: [`Mengidentifikasi tiga mekanisme kelompok ${n}.`, `Menjelaskan dua interaksi kelompok ${n}.`],
    })) },
    mingguan: { minggu: Array.from({ length: 16 }, (_, index) => {
      const minggu = index + 1;
      return {
        ...meta(300 + minggu), minggu_ke: minggu,
        sub_cpmk_kode: `Sub-CPMK-${minggu <= 5 ? 1 : minggu <= 10 ? 2 : 3}`,
        indikator: `Menjelaskan mekanisme pada kasus minggu ${minggu}.`,
        kriteria_penilaian: "Ketepatan analisis dan argumentasi; penilaian studi kasus.",
        metode_pembelajaran: "Case-based learning dan diskusi kelompok",
        bentuk_luring: "Kuliah interaktif dan diskusi kasus",
        bentuk_daring: "Forum LMS dan kuis formatif",
        pengalaman_belajar: "Menyusun peta konsep secara mandiri",
        materi_pustaka: minggu === 8 ? "UTS" : minggu === 16 ? "UAS" : `Farmakodinamik pertemuan ${minggu} [Pustaka Fixture, Bab ${minggu}]`,
        bobot_penilaian: minggu === 4 || minggu === 9 ? 30 : minggu === 16 ? 40 : 0,
      };
    }) },
    penilaian: { komponen: [1, 2, 3].map((n) => ({
      ...meta(400 + n), nama: `Analisis kasus ${n}`, jenis: "tugas", instrumen: "Lembar analisis kasus",
      bobot_persen: n === 3 ? 40 : 30, sub_cpmk_kode: `Sub-CPMK-${n}`, minggu_ke: [4, 9, 16][n - 1],
      rubrik: {
        jenis: "analitik", jumlah_level_skala: 4, label_skala: ["Kurang", "Cukup", "Baik", "Sangat baik"],
        kriteria: [
          { kriteria: "Ketepatan mekanisme", bobot: 60,
            deskriptor: ["Mekanisme tidak tepat", "Satu mekanisme tepat", "Dua mekanisme tepat", "Semua mekanisme tepat dan beralasan"] },
          { kriteria: "Argumentasi ilmiah", bobot: 40,
            deskriptor: ["Tanpa bukti", "Bukti terbatas", "Bukti relevan", "Bukti relevan dan kritis"] },
        ],
      },
    })) },
  };
}

function makeSession(id) {
  const stageStatus = { 1: "draft", 2: "accepted", 3: "edited", 4: "pinned", 5: "accepted", 6: "accepted" }[id];
  return {
    id, institusi_id: 1, mk_id: mk.id, kode_mk: mk.kode_mk, nama_mk: mk.nama,
    sumber: "baru", tahap: "penilaian", status: id >= 5 ? "committed" : id === 1 ? "berjalan" : "selesai",
    revisi: 10, status_bagian: Object.fromEntries(Object.keys(ITEM_KEYS).map((stage) => [stage, stageStatus])),
    draf: makeDraft(), catatan_validasi: {}, konteks_tambahan: null,
    rps_version_id: id >= 5 ? 100 + id : null, rps_status: id === 6 ? "approved" : id === 5 ? "review" : null,
    can_reopen: id === 5, user_id: 1, created_at: DATE, updated_at: DATE,
  };
}

function stateFor(token) {
  if (!states.has(token)) states.set(token, new Map([1, 2, 3, 4, 5, 6].map((id) => [id, makeSession(id)])));
  return states.get(token);
}
function json(res, status, body) {
  res.writeHead(status, { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" });
  res.end(JSON.stringify(body));
}
function collection(res, data) {
  json(res, 200, { data, meta: { total: data.length, per_page: 200, current_page: 1, last_page: 1 } });
}
function check(condition, message, status = 422) {
  if (!condition) throw Object.assign(new Error(message), { status });
}
async function bodyOf(req) {
  let text = "";
  for await (const chunk of req) {
    text += chunk;
    check(text.length <= 100_000, "Fixture request too large", 413);
  }
  try { return text ? JSON.parse(text) : {}; }
  catch { throw Object.assign(new Error("Invalid JSON"), { status: 400 }); }
}
function markDownstream(session, stage, item) {
  const draft = session.draf;
  const codes = stage === "cpmk"
    ? draft.sub_cpmk.sub_cpmk.filter((sub) => sub.cpmk_kode === item.kode).map((sub) => sub.kode)
    : stage === "sub_cpmk" ? [item.kode] : [];
  if (stage === "cpmk") {
    for (const sub of draft.sub_cpmk.sub_cpmk) if (codes.includes(sub.kode)) sub._needs_review = true;
  }
  for (const child of [...draft.mingguan.minggu, ...draft.penilaian.komponen]) {
    if (codes.includes(child.sub_cpmk_kode)) child._needs_review = true;
  }
}

const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${HOST}:${PORT}`);
    const route = url.pathname;
    if (req.method === "GET" && route === `${PREFIX}/health`) {
      return json(res, 200, { data: { status: "ok", fixture: "generator-ui" } });
    }
    const token = /^Bearer (.+)$/.exec(req.headers.authorization ?? "")?.[1];
    check(token?.startsWith("generator-e2e-") && !token.startsWith("generator-e2e-stale-"), "Unauthenticated.", 401);
    if (req.method === "GET" && route === `${PREFIX}/auth/me`) return json(res, 200, { data: user });
    const sessions = stateFor(token);

    if (req.method === "GET") {
      if (route === `${PREFIX}/mata-kuliah/1`) return json(res, 200, { data: mk });
      if (route === `${PREFIX}/mata-kuliah`) return collection(res, [mk]);
      if (route === `${PREFIX}/cpl`) return collection(res, cpl);
      if (route === `${PREFIX}/taksonomi`) return collection(res, taksonomi);
      if (route === `${PREFIX}/konfigurasi-aturan`) return collection(res, aturan);
      if (route === `${PREFIX}/generate-sessions`) {
        return collection(res, [...sessions.values()].filter((session) => !url.searchParams.has("status") || session.status === url.searchParams.get("status")));
      }
      // Dashboard data may be prefetched by Next's real navigation links.
      if (route === `${PREFIX}/kurikulum` || route === `${PREFIX}/rps-versions`) return collection(res, []);
      if (route === `${PREFIX}/ai/pengaturan`) return json(res, 200, { data: { profil_aktif: "fixture" } });
    }

    const match = /^\/api\/v1\/generate-sessions\/([1-6])(?:\/(pin|unpin|item-pin|item-candidate|item-apply|reopen))?$/.exec(route);
    check(match, `Unhandled fixture route: ${req.method} ${route}`, 404);
    const session = sessions.get(Number(match[1]));
    const action = match[2];
    if (req.method === "GET" && !action) return json(res, 200, { data: session });
    check(action && req.method === (action === "item-pin" ? "PATCH" : "POST"), "Method not allowed", 405);
    const body = await bodyOf(req);

    if (action === "reopen") {
      check(session.can_reopen && session.status === "committed" && session.rps_status !== "approved", "RPS yang sudah disetujui prodi tidak dapat dikembalikan ke draf.");
      check(typeof body.catatan === "string" && body.catatan.trim().length > 0 && body.catatan.length <= 2000, "Alasan wajib diisi.");
      session.status = "berjalan";
      session.rps_status = "draft";
      session.can_reopen = false;
      session.revisi++;
      return json(res, 200, { data: session });
    }

    check(session.status !== "committed" && session.rps_status !== "approved", "RPS sudah di-commit. Kembalikan ke draf sebelum menyunting.");
    const { stage } = body;
    check(Object.hasOwn(ITEM_KEYS, stage), "Invalid stage");
    if (action === "pin" || action === "unpin") {
      if (action === "pin" || session.status_bagian[stage] === "pinned") {
        session.status_bagian[stage] = action === "pin" ? "pinned" : "accepted";
        session.revisi++;
      }
      return json(res, 200, { data: session });
    }

    check(session.status_bagian[stage] !== "pinned", "Tahap disematkan. Lepas sematan tahap terlebih dahulu.");
    const items = session.draf[stage][ITEM_KEYS[stage]];
    const index = items.findIndex((item) => item._id === body.item_id);
    check(index >= 0, "Item not found", 404);
    const item = items[index];
    if (action === "item-pin") {
      check(typeof body.pinned === "boolean", "pinned must be boolean");
      item._pin = body.pinned;
      session.revisi++;
      return json(res, 200, { data: session });
    }
    check(!item._pin, "Item disematkan. Lepas sematan terlebih dahulu.");
    check(stage === "cpmk" || stage === "sub_cpmk", "Candidate fixture supports CPMK/Sub-CPMK only");
    if (action === "item-candidate") {
      const before = publicItem(item);
      const after = { ...before, deskripsi: `${before.deskripsi} (usulan deterministik).` };
      return json(res, 200, { data: {
        stage, item_id: item._id, before, after, base_revisi: session.revisi,
        usage: { model: "fixture-no-ai", provider: "fixture", estimated_usd: 0 },
      } });
    }
    check(Number.isInteger(body.base_revisi) && body.base_revisi === session.revisi, "Draf sudah berubah sejak usulan dibuat.", 409);
    check(body.after && typeof body.after.deskripsi === "string" && body.after.deskripsi.trim(), "Invalid candidate");
    const before = publicItem(item);
    // Fail fast on an incorrect Server Action payload, instead of silently
    // accepting lost IDs/mappings or metadata unlike Laravel's array rules.
    check(isDeepStrictEqual({ ...body.after, deskripsi: before.deskripsi }, before), "Candidate changed identity/mapping or contained unexpected fields");
    items[index] = { ...structuredClone(body.after), _id: item._id, _pin: item._pin };
    markDownstream(session, stage, items[index]);
    session.revisi++;
    return json(res, 200, { data: session });
  } catch (error) {
    const status = error.status ?? 500;
    if (status === 500) console.error(error);
    json(res, status, { message: error.message });
  }
});

server.listen(PORT, HOST, () => console.log(`Generator UI fixture listening on http://${HOST}:${PORT} (no real API/AI)`));
const stop = () => {
  server.close(() => process.exit(0));
  server.closeAllConnections();
};
process.once("SIGTERM", stop);
process.once("SIGINT", stop);