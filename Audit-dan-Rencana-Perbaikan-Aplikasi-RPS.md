# Audit dan Rencana Perbaikan Aplikasi RPS

**Tanggal audit:** 5 September 2026  
**Cakupan:** `curriculum-service`, `curriculum-web`, subsistem AI, data penggunaan produksi, dependensi, CI/CD, dan deployment.  
**Prototipe UI:** [Buka prototipe Generator RPS](Prototipe-UI-Generator-RPS.html)

> Dokumen ini membedakan fakta yang telah diverifikasi dari risiko dan usulan. Harga model adalah harga resmi OpenAI per tanggal audit dan harus disinkronkan secara berkala.

## Status Implementasi Pascaaudit

Temuan di bawah dipertahankan sebagai baseline historis. Perbaikan berikut sudah diterapkan dan tervalidasi lokal, tetapi **belum dideploy**:

- Seluruh API bisnis dilindungi Sanctum dan konteks tenant; health dan login tetap publik. Proxy unduhan browser meneruskan Bearer token secara server-side.
- Isolasi tenant mencakup query, route model, pemilihan mata kuliah, serta lookup institusi. Regression test lintas-tenant tersedia.
- Candidate patch memakai validasi schema per tahap, preservasi identitas, optimistic revision check di dalam transaksi dengan row lock, pin guard, dan propagasi dampak transitif.
- Keluaran AI terpotong (`finish_reason=length`) dideteksi, dicoba ulang dengan token lebih besar, dan seluruh token percobaan tetap dihitung.
- Cache AI dipisahkan menurut tenant serta versi prompt/schema/sumber. Model berbayar tanpa katalog harga ditolak sebelum provider dipanggil.
- Kuota AI mempunyai reservasi biaya dalam transaksi, tetapi belum dapat diklaim sebagai batas biaya yang ketat: kunci masih per-kredensial sementara agregat biaya per-tenant, estimasi belum mencakup kenaikan token retry, dan rekonsiliasi biaya gagal/fallback masih perlu diperbaiki.
- Workspace candidate patch sudah diperbaiki agar tetap terlihat pada tahap draft/accepted/edited. Sebelumnya kondisi `!locked` menyembunyikan ceklis dan kontrol per-butir setelah persetujuan tahap. Dialog memiliki focus trap/restoration, label aksesibel, dan aksi semat massal melaporkan kegagalan parsial.
- Login, API, AI, dan impor memiliki rate limit terarah. Security headers aktif di Next.js dan Caddy.
- Image aplikasi dapat dipin dengan `IMAGE_TAG` SHA. Backup/restore database serta `storage/app` dilengkapi checksum, retensi, dan konfirmasi restore.
- CI menjalankan backend test, frontend lint/build, Playwright smoke E2E, serta dependency audit sebelum image dibangun.

Validasi terakhir:

| Pemeriksaan | Hasil pascaperbaikan |
| --- | --- |
| PHPUnit backend | Lulus: 99 test, 855 assertion (termasuk lifecycle, sematan, kandidat, dan proteksi capaian RPS approved) |
| Candidate patch | Ditambah tes kandidat tanpa mutasi, patch parsial, pemetaan CPL tetap, serta proteksi sematan |
| AI pricing/cache/budget | Lulus: 8 test, 22 assertion |
| Frontend ESLint + build | Lulus, tanpa warning konvensi middleware |
| Playwright standalone | 10 skenario: 8 regresi generator + 2 smoke test; SSR dan server action nyata, API fixture lokal terisolasi (bukan provider AI nyata) |
| NPM audit produksi dan lengkap | 0 vulnerability |
| Compose + skrip backup/restore | Lulus validasi sintaks |

**Koreksi kesimpulan:** klaim sebelumnya bahwa seluruh audit telah selesai terlalu luas. Smoke test login/header tidak membuktikan render generator pada status accepted/edited/pinned/committed. Uji restore juga belum dijalankan; kelulusan sintaks bukan bukti backup dapat dipulihkan.

### Audit ulang commit dan render generator

- HEAD lokal dan `origin/main` masih `ac086d4`. Riwayat mencakup `f1fe22b` (workspace), `9b2dff3` (AI per-butir), `af29632` (UTS/UAS), dan `ac086d4` (OpenAI reasoning). Seluruh perbaikan audit baru masih working tree; **belum commit/push/deploy** atas permintaan pengguna.
- Akar regresi terverifikasi: `generated && !locked && !committed` membuat workspace hilang ketika tahap accepted/edited/pinned. Ini bukan bukti semua fitur terhapus dari Git; cabang render berbeda menutup fitur yang sudah ada.
- Workspace kini tersedia pada semua tahap berisi selama sesi belum committed. Tahap pinned tampil baca-saja dengan tombol lepas sematan. Ceklis digunakan untuk memilih butir/sematan massal; AI tetap memberi satu kandidat setiap kali, bukan regenerasi massal terselubung.
- Tampilan rinci lama tetap tersedia melalui “Lihat rincian lengkap”: pemetaan CPL/taksonomi, indikator Sub-CPMK, rincian mingguan, dan rubrik penilaian. Editor manual, matriks, diagnostik, impor, konteks dosen, dan percakapan AI tidak dihapus.
- Butir pinned tidak dapat ditimpa melalui apply, sunting manual, penolakan, atau regenerasi seluruh tahap. Regenerasi massal ditolak jika ada sematan; AI per-butir tetap tersedia untuk butir lain. Sematan massal dijalankan berurutan, mutasi backend memakai kunci/revisi.
- Patch parsial mempertahankan field opsional yang tidak dikirim serta kode/pemetaan induk. Perubahan capaian menandai dependensi untuk ditinjau tanpa mengubah rumusannya. Commit ditolak selama tanda perlu tinjau belum disetujui kembali.
- “Kembalikan ke draf” tersedia di generator dan detail dokumen hanya untuk sesi committed dengan RPS draft/review/revisi yang belum pernah disetujui. Alasan wajib; aktor berasal dari pengguna autentik; riwayat dicatat sebagai `buka_draf`. Status approved, `approved_at`, maupun riwayat `setujui` masing-masing memblokir pembukaan.
- Pembukaan draf tidak menghapus dokumen, mingguan, rubrik, maupun riwayat. Pengajuan review ditarik. Dokumen tetap menunjukkan commit terakhir sampai commit ulang atomik ke ID/versi yang sama, tanpa penumpukan anak. Pengajuan persetujuan diblokir selama staging dibuka.
- Loop login akibat cookie kedaluwarsa diperbaiki. Lookup taksonomi/aturan generator memakai institusi sesi, bukan angka 1.
- Dokumen audit semula ikut diabaikan aturan root `/*.md`; pengecualian ditambahkan agar dapat ditinjau dalam commit berikutnya (belum di-stage/commit).

### Audit koherensi menyeluruh (putaran terakhir)

- Empat kegagalan test ditemukan dan diperbaiki: (1) regenerasi per-butir menolak kandidat AI yang mengubah kunci identitas — kini identitas dipulihkan senyap seperti semantik apply, kandidat dinormalisasi lalu divalidasi sebelum kontrak non-strict; (2–3) endpoint PUT prompt-templates mengembalikan 201 alih-alih 200 (gotcha Laravel: resource atas model `wasRecentlyCreated`); (4) pembanding atribut PromptResetTest memakai state in-memory, kini `fresh()`.
- **RPS approved bersifat final:** `RpsVersion::pernahDisetujui()` (status approved / `approved_at` / riwayat `setujui`) kini memblokir DELETE dokumen (422) dan generate rincian pertemuan, selain jalur reopen yang sudah ada. Dua test lifecycle baru membuktikannya; RPS committed non-approved tetap dapat dihapus.
- **Hardcode tenant frontend dihapus total:** `EnforceTenantContext` menolak `institusi_id` asing (403) dan menyuntikkan tenant user ke setiap request, sehingga seluruh `DEFAULT_INSTITUSI = 1` (21 file, ±40 titik: body aksi, query param, form multipart, signature `listReferensi`/`syncReferensi`) dihapus dari curriculum-web. Grep bersih; tenant kini murni ditentukan server dari sesi login.
- Kohesi berkas baru diverifikasi: `GenerationContract` (batas Sub-CPMK ≤ min(14, minggu belajar), direktif server tak-tersimpan, validasi strict/non-strict) terpasang di semua jalur generate/accept/apply/commit; cache embedding ber-identity + anti-stampede tidak pernah menyimpan hasil mock; stack reset prompt (versioned append-only, mutex per tenant, endpoint reset) konsisten dengan katalog runtime.
- Gerbang akhir: backend PHPUnit **138 test / 1.288 assertion lulus**; frontend ESLint bersih, build produksi lulus, Playwright **10/10 lulus**.
- **Status rilis:** HEAD lokal & `origin/main` tetap `ac086d4`; produksi sempat di-redeploy dengan image lama pada commit yang sama — artinya SEMUA perbaikan di atas masih working tree lokal, belum ada di produksi.

### Backlog nyata — belum dinyatakan selesai

1. **Capaian lintas-versi:** CPMK/Sub-CPMK masih entitas bersama per MK. Commit yang hendak mengubah capaian bersama ketika ada RPS approved kini ditolak secara konservatif. Dibutuhkan snapshot/versi capaian tersendiri untuk revisi kurikulum tanpa mengubah dokumen terdahulu; ini belum diimplementasikan.
2. **Budget/rekonsiliasi AI:** reservasi perlu kunci per-tenant, perhitungan semua retry yang membesar, dan pencatatan biaya provider gagal sebelum fallback. Embedding perlu disatukan dalam kontrol anggaran. Angka biaya belum merupakan tagihan provider yang direkonsiliasi.
3. **Hak akses lengkap:** Sanctum dan isolasi tenant sudah teruji, tetapi audit izin per-peran untuk seluruh aksi bisnis/approval belum tuntas. Hardcode institusi frontend SUDAH dihapus seluruhnya (tenant dari sesi via `EnforceTenantContext`). Autentikasi tidak sama dengan otorisasi lengkap.
4. **Siklus artefak approved menyeluruh:** jalur generator, reopen, DELETE dokumen, dan generate rincian pertemuan kini dijaga `pernahDisetujui()`; jalur mutasi master bersama (CPMK/Sub-CPMK lintas dokumen) masih perlu diaudit terhadap immutabilitas dokumen approved.
5. **Riwayat/referensi per butir:** belum ada penyimpanan snapshot setiap kandidat beserta pemulihan revisi; label palsu “Versi sebelumnya tercatat” telah dihapus. Grounding/citation terverifikasi per-butir dan provenance manual/AI belum lengkap; UI tidak lagi melabeli semua baris sebagai AI.
6. **Backup/restore:** skrip hanya lolos sintaks; pipeline POSIX dapat menyamarkan kegagalan dump, retensi terlalu luas, serta konsistensi DB/berkas dan pemulihan gagal perlu diuji di salinan nonproduksi. Belum aman dinyatakan siap operasi hanya dari checksum.
7. **Verifikasi rilis:** tes browser memakai backend fixture, tes Laravel memakai SQLite; belum uji konkuren MySQL, provider berbayar nyata, migrasi produksi, restore drill, atau smoke publik setelah rilis. Tidak ada deployment dalam audit ulang ini.

Retrieval vector index tetap peningkatan skala lanjutan. Backlog di atas dipisahkan dari perbaikan render/lifecycle agar tidak lagi ada klaim “semuanya selesai” berdasarkan pengujian yang sempit.

## 1. Ringkasan Eksekutif

Fondasi aplikasi sudah kuat: domain OBE cukup matang, generator dibagi menjadi empat tahap, tersedia multi-provider dan BYOK, keluaran AI diproses sebagai draf sebelum commit, terdapat validasi deterministic, dukungan RAG/grounding, audit interaksi, dan build produksi frontend berhasil.

Kesenjangan terbesar bukan pada kemampuan membuat RPS, melainkan pada kendali produksi dan ergonomi penyuntingan:

1. Hampir seluruh API bisnis belum dilindungi autentikasi/otorisasi; sebelum aplikasi dibuka ke pengguna nyata, ini adalah prioritas P0.
2. Frontend masih memakai `institusi_id = 1` secara luas sehingga implementasi efektif masih single-tenant.
3. Generator mengganti satu tahap utuh saat regenerasi. Pengguna belum dapat mempertahankan bagian yang bagus dan meminta AI hanya mengubah item atau field tertentu.
4. Perhitungan biaya dapat mencatat model live sebagai gratis; budget guard dan laporan biaya karena itu belum dapat dipercaya.
5. Test suite backend hanya dua test kerangka, frontend belum memiliki test, dan CI belum menjalankan test/lint/audit sebelum membangun image.
6. Grounding tersedia secara teknis, tetapi sesi produksi yang diperiksa melewati grounding karena tidak memiliki dokumen `sumber_konten`.
7. Dependensi memiliki advisory keamanan dan lint frontend masih gagal.

Arah yang disarankan adalah **AI sebagai penyusun usulan perubahan**, bukan pengganti draf. Setiap tindakan AI menghasilkan candidate patch, diff, dampak dependensi, biaya estimasi, dan riwayat revisi. Pengguna memilih perubahan yang diterapkan.

## 2. Metode dan Bukti

Audit dilakukan dengan membaca jalur kode yang mengontrol perilaku, memeriksa route dan middleware, menjalankan quality gate, memeriksa advisory dependensi, serta membaca statistik agregat produksi tanpa mengambil prompt, respons, atau API key.

Hasil executable:

| Pemeriksaan | Hasil |
| --- | --- |
| PHPUnit backend | Lulus: 2 test, 2 assertion; keduanya test kerangka |
| Build produksi Next.js | Lulus |
| ESLint frontend | Gagal: 1 error React dan 2 warning |
| NPM audit | 5 vulnerability: 4 high, 1 moderate |
| Composer audit | 16 advisory pada `guzzlehttp/guzzle` dan `league/commonmark` |
| Route bisnis | Terverifikasi mayoritas hanya memiliki middleware `api` |
| Prototipe UI | Lulus interaksi; desktop 1440 px dan mobile 390 px tanpa overflow horizontal |

Catatan koreksi audit:

- File `.env` lokal tidak terlacak Git. Tidak ada bukti secret bocor melalui repository.
- Penggunaan `guarded = []` bukan otomatis vulnerability karena controller yang diperiksa memakai payload tervalidasi. Tetap dianjurkan memakai `$fillable` sebagai defense in depth.
- Parser JSON generator melempar `GeneratorException` saat keluaran tidak valid; tidak diam-diam menerima array kosong.
- Nama GPT-5.6 dan GPT-6 yang ditemukan adalah model resmi pada tanggal audit.

## 3. Kekuatan yang Perlu Dipertahankan

- Pemisahan Laravel API dan Next.js cukup jelas.
- Alur generator `CPMK → Sub-CPMK → mingguan → penilaian` sesuai mental model penyusunan RPS.
- Commit akhir menggunakan transaksi dan keluaran AI tidak langsung menjadi dokumen terbit.
- Multi-provider, profil model, BYOK terenkripsi, prompt catalog, cache, dan pencatatan interaksi sudah menjadi fondasi yang baik.
- Ada deterministic self-check untuk kelengkapan dan bobot 100%.
- RAG dan cross-provider validator sudah tersedia.
- OBE, approval, versi RPS, dan publikasi ke RPS Core sudah jauh lebih matang daripada prototipe generator biasa.

## 4. Temuan Terverifikasi

### P0 — Wajib sebelum produksi luas

#### 4.1 API bisnis tidak terlindungi secara menyeluruh

Hanya kelompok route tertentu yang memakai `auth:sanctum`. Route kurikulum, generator, AI, approval, RAG, OBAEI, dan governance sebagian besar berada di luar perlindungan tersebut.

**Dampak:** pembacaan/perubahan data tanpa identitas pengguna, konsumsi API AI tanpa kontrol, lintas-tenant, dan audit actor yang tidak sahih.

**Perbaikan:**

- Lindungi semua route nonpublik dengan autentikasi dan permission/policy.
- Turunkan `institusi_id` dari identity/token, bukan body atau konstanta frontend.
- Terapkan policy per resource dan cek kepemilikan tenant pada query, bukan setelah record ditemukan.
- Tambahkan rate limit khusus AI, unggah dokumen, ekspor, dan endpoint mahal.
- Pertahankan hanya health check dan endpoint publik yang memang dibutuhkan sebagai route anonim.

#### 4.2 Isolasi tenant belum konsisten

Ditemukan 62 penggunaan tenant ID `1` pada frontend. Ini membuat fitur multi-tenant pada skema/backend tidak benar-benar terpakai.

**Perbaikan:** buat `TenantContext` server-side dari session, hapus `institusi_id` dari kontrak aksi pengguna biasa, dan tambahkan integration test yang membuktikan tenant A tidak dapat membaca/mengubah objek tenant B.

#### 4.3 Akuntansi biaya model live dapat bernilai nol

Model yang dipilih dari katalog live dengan referensi `provider::model-id` dapat memperoleh pricing nol. Dashboard, budget guard, dan laporan biaya kemudian menganggap penggunaan model tersebut gratis.

**Perbaikan:**

- Simpan katalog harga berversi dengan `effective_at`, currency, harga input/cached/output, dan sumber.
- Tolak aktivasi model berbayar bila harga tidak dikenal; jangan default ke nol.
- Rekonsiliasi log internal dengan usage provider setiap hari.
- Bedakan `estimated_cost`, `provider_reported_cost`, dan `billing_status`.

#### 4.4 Fallback ke mock menyamarkan kegagalan provider

Saat provider nyata gagal, fallback dapat mengganti identitas efektif menjadi `mock`. Percobaan provider awal dan alasan fallback tidak terlihat jelas pada log akhir.

**Perbaikan:** catat `requested_provider/model`, `effective_provider/model`, `fallback_reason`, `attempt_no`, latency, dan token tiap attempt. Pada mode produksi, fallback mock harus default **off**; kegagalan ditampilkan sebagai kegagalan yang dapat dicoba ulang.

### P1 — Keandalan dan kualitas inti

#### 4.5 Regenerasi masih bergranularitas satu tahap

Endpoint generate hanya menerima `stage`; draf lokal yang belum disimpan tidak ikut dikirim. Regenerasi dapat menimpa seluruh hasil tahap dan menghilangkan bagian yang sudah baik.

**Perbaikan utama:** candidate patch per item/field, pin, diff, selective apply, optimistic locking, dan revision history. Rancangan lengkap ada pada Bagian 7.

#### 4.6 Cakupan pengujian belum bermakna

Dua test backend yang ada hanya memeriksa halaman root dan `true === true`; belum ada frontend test.

**Minimum test P1:**

- Auth, permission, dan isolasi tenant.
- Generate/accept/reject/pin/commit serta konflik revisi.
- Parser JSON, schema keluaran, fallback provider, budget, cache, dan accounting.
- Grounding dengan/tanpa sumber.
- Approval, versioning, publish idempoten, dan rollback.
- Playwright untuk alur membuat RPS, selective apply, dan recovery saat AI gagal.

#### 4.7 Dependency advisory belum ditutup

NPM melaporkan 4 high + 1 moderate. Composer melaporkan 16 advisory pada dua paket transitif.

**Perbaikan:** lakukan upgrade terkontrol, ulangi test/build, dan jadikan `npm audit --omit=dev` serta `composer audit --locked` quality gate. Bila advisory belum dapat ditutup, catat exception dengan owner dan expiry date.

#### 4.8 CI belum menjadi quality gate

Workflow saat ini membangun dan mendorong image tanpa memastikan test, lint, build, dan audit lulus.

**Perbaikan:** pisahkan job `backend-test`, `frontend-lint-build`, `dependency-audit`, lalu izinkan image publish hanya setelah semuanya hijau. Tambahkan migration check dan smoke test image.

#### 4.9 Grounding belum aktif secara operasional

Sesi committed yang diperiksa menghasilkan 5 CPMK, 10 Sub-CPMK, 16 minggu, dan 10 komponen penilaian, tetapi grounding dilewati pada empat tahap karena tidak ada dokumen `sumber_konten`.

**Perbaikan:**

- Jadikan status sumber terlihat sebelum generate: tersedia, belum diindeks, gagal, atau kosong.
- Untuk klaim yang membutuhkan dasar ilmiah, blok status “siap diajukan” bila grounding belum dilakukan.
- Bedakan rujukan regulasi, dokumen institusi, dan sumber materi kuliah.
- Tampilkan citation per item dan versi dokumen sumber.

#### 4.10 Pipeline grounding tidak efisien pada skala besar

Query embedding dapat dilakukan per klaim; retrieval memuat chunk dan menghitung cosine di PHP terhadap embedding JSON MySQL.

**Perbaikan:** batch embedding untuk seluruh klaim satu tahap, deduplikasi klaim, cache query embedding, dan pindahkan pencarian vektor ke database/vector store saat volume menuntut. Validator AI dipakai untuk klaim ambigu, bukan semua aturan struktural.

### P2 — Operasional dan UX

#### 4.11 Budget check advisory dan rentan race condition

Pengecekan sebelum request tidak menjamin dua request paralel tidak melampaui anggaran.

**Perbaikan:** lakukan reservasi budget atomik sebelum request, finalisasi dengan biaya aktual, lalu lepaskan selisih. Terapkan batas per tenant, pengguna, fitur, dan periode.

#### 4.12 Cache AI perlu identitas tenant dan versi eksplisit

Cache key belum menyertakan tenant secara eksplisit. Walau prompt sering mengandung konteks tenant, batas isolasinya harus eksplisit. Cache hit juga mencatat kembali token asal walau biaya nol sehingga metrik konsumsi ambigu.

**Perbaikan:** sertakan tenant, prompt version, schema version, source version, model, dan parameter. Pisahkan `provider_tokens` dari `logical_tokens_reused`; tandai `cache_hit`.

#### 4.13 Lint frontend gagal dan dialog native masih dipakai

Error lint berada di penyusunan JSX dalam `try/catch` pada dashboard; ditemukan 10 pemakaian dialog browser native.

**Perbaikan:** pindahkan transformasi data keluar dari `try/catch`, gunakan error state yang eksplisit, dan ganti seluruh `confirm/alert/prompt` dengan `ConfirmDialog`, form modal, dan `StatusMessage`.

#### 4.14 Readiness deployment belum lengkap

Database memiliki healthcheck, tetapi API/web belum memiliki readiness yang memadai. Belum terlihat resource limit, prosedur backup/restore otomatis, dan pin image immutable yang konsisten.

**Perbaikan:** healthcheck API/web, startup ordering berbasis sehat, CPU/memory limit, backup terenkripsi dengan uji restore, image digest, log terstruktur, alert error rate/latency/queue, serta runbook rollback.

## 5. Risiko yang Masih Perlu Diverifikasi

Butir berikut bukan dinyatakan sebagai bug sampai dibuktikan lewat pengujian khusus:

- Efektivitas policy/tenant scope setelah auth penuh dipasang.
- Ketahanan terhadap prompt injection dari dokumen RAG.
- Retensi prompt/respons dan kepatuhan privasi data akademik.
- Akurasi token dari tiap provider selain OpenAI.
- Recovery queue ketika ingestion atau embedding berhenti di tengah jalan.
- Backup point-in-time dan kemampuan restore pada infrastruktur aktual.
- Performa retrieval saat jumlah chunk tumbuh ke ratusan ribu.

## 6. Arsitektur AI yang Disarankan

### 6.1 Prinsip

1. **Deterministik untuk aturan:** bobot 100%, referensi kode, jumlah minggu, tipe data, dan dependency graph diperiksa kode biasa.
2. **AI untuk kualitas semantik:** kejelasan rumusan, kesesuaian taksonomi, koherensi materi, alternatif asesmen, dan ringkasan.
3. **Human-in-the-loop:** AI selalu menghasilkan candidate; pengguna memilih penerapan.
4. **Grounded by default:** citation dibawa bersama setiap field yang bersumber.
5. **Task-based routing:** model dipilih berdasarkan risiko tugas, bukan satu model untuk semuanya.
6. **Privacy routing:** data mahasiswa/PII tetap ke model lokal; materi non-PII boleh memakai cloud sesuai kebijakan.

### 6.2 Routing model

| Tugas | Model awal | Alasan |
| --- | --- | --- |
| Perbaikan redaksi, klasifikasi, ekstraksi, alternatif singkat | `gpt-5.6-luna` | Biaya rendah, volume tinggi |
| Generate CPMK/Sub-CPMK/mingguan/penilaian | `gpt-5.6-luna`, naik ke Terra bila quality gate gagal | Default paling ekonomis |
| Audit koherensi lintas tahap dan konflik sulit | `gpt-5.6-terra` | Keseimbangan kualitas dan biaya |
| Kasus kompleks yang gagal dua kali atau review final berisiko tinggi | `gpt-5.6-sol` | Eskalasi terbatas |
| Embedding | `text-embedding-3-small` | Murah; evaluasi recall sebelum memakai large |
| Validasi struktur | Tanpa LLM | Lebih murah dan dapat diuji |
| Pekerjaan massal noninteraktif | Batch API | Harga sekitar 50% standard |

Jangan memakai Sol atau model flagship untuk semua tindakan. Quality gate dan eskalasi jauh lebih ekonomis daripada routing seragam.

## 7. Desain AI Granular: Candidate Patch

### 7.1 Perilaku pengguna

- Pengguna memilih satu/beberapa item atau satu field.
- Pengguna memilih tindakan: perbaiki redaksi, buat alternatif, naikkan taksonomi, ringkas, grounding, atau periksa konsistensi.
- Sistem mengirim **snapshot revisi dasar** dan konteks dependensi.
- AI mengembalikan operasi perubahan terstruktur, bukan tahap penuh.
- UI menampilkan before/after, alasan, citation, estimasi biaya, confidence, dan dampak hilir.
- Pengguna mencentang operasi yang diterapkan.
- Item tersemat tidak dapat diubah sampai sematan dilepas secara eksplisit.
- Apply membuat revision baru; undo mengembalikan revision, bukan mengandalkan state browser.

### 7.2 Bentuk data item

```json
{
  "id": "subcpmk_01H...",
  "revision": 7,
  "kode": "SC-01",
  "deskripsi": "...",
  "cpmk_kode": "CPMK-01",
  "taksonomi_kode": ["C4"],
  "meta": {
    "pinned": true,
    "source": "ai_edited",
    "grounding_status": "verified",
    "citations": ["doc_12:chunk_8"],
    "updated_by": 41,
    "updated_at": "2026-09-05T06:30:00Z"
  }
}
```

### 7.3 Bentuk candidate

```json
{
  "candidate_id": "cand_01H...",
  "session_id": 18,
  "stage": "sub_cpmk",
  "base_revision": 7,
  "operations": [
    {
      "op": "replace",
      "item_id": "subcpmk_01H...",
      "field": "deskripsi",
      "before": "Menjelaskan ...",
      "after": "Menganalisis ...",
      "reason": "Rumusan menjadi terukur",
      "citations": ["doc_12:chunk_8"],
      "confidence": 0.91
    }
  ],
  "impact": {
    "requires_review": ["minggu_03", "minggu_04"],
    "blocked_by_pins": [],
    "auto_mutated": []
  },
  "usage": {
    "model": "gpt-5.6-luna",
    "estimated_usd": 0.0012
  }
}
```

### 7.4 Endpoint yang diusulkan

```text
POST /api/v1/generate-sessions/{id}/candidates
GET  /api/v1/generate-sessions/{id}/candidates/{candidate}
POST /api/v1/generate-sessions/{id}/candidates/{candidate}/apply
POST /api/v1/generate-sessions/{id}/candidates/{candidate}/reject
PATCH /api/v1/generate-sessions/{id}/items/{item}/pin
GET  /api/v1/generate-sessions/{id}/revisions
POST /api/v1/generate-sessions/{id}/revisions/{revision}/restore
```

`apply` wajib memakai `base_revision`. Bila draf sudah berubah, API mengembalikan `409 Revision conflict` dan meminta pengguna meninjau diff baru. Semua operasi diterapkan dalam transaksi.

### 7.5 Dependency graph

- CPMK berubah → tandai Sub-CPMK, minggu, dan penilaian terkait untuk review.
- Sub-CPMK berubah → tandai minggu dan komponen terkait.
- Minggu berubah → evaluasi bobot dan keterlacakan penilaian.
- Komponen berubah → jalankan ulang total bobot dan matriks CPMK.

Dependensi **tidak diubah otomatis** kecuali pengguna menyetujui candidate terpisah.

## 8. Konsep UI/UX Profesional

Prototipe yang dapat dibuka langsung: [Prototipe-UI-Generator-RPS.html](Prototipe-UI-Generator-RPS.html).

### 8.1 Susunan layar

- Sidebar aplikasi yang tenang dan familiar.
- Header ringkas berisi identitas mata kuliah, status simpan, audit, dan ajukan.
- Stepper empat tahap agar posisi dan progres selalu terlihat.
- Quality strip: kelengkapan, keterlacakan, grounding, item perlu tinjau, dan estimasi aksi.
- Editor utama berupa tabel padat; tiap item memiliki pilihan, pin, dan tindakan AI.
- Panel AI kontekstual di kanan; hasil selalu berupa pratinjau diff.
- Mobile menjadi satu kolom, stage tetap dapat digeser, dan panel AI berpindah setelah daftar.

### 8.2 Aturan interaksi

- `Generate seluruh tahap` tetap tersedia sebagai tindakan sekunder dan harus meminta konfirmasi dampak.
- Tindakan default adalah pada selection, bukan seluruh tahap.
- Autosave hanya menyimpan edit manual; AI candidate tidak diterapkan otomatis.
- Status harus eksplisit: manual, AI, AI+disunting, tergrounding, perlu tinjau.
- Pin melindungi item dan field penting.
- Undo/riwayat memakai revisi server.
- Error provider tidak boleh menghilangkan draf atau menutup panel.
- Estimasi biaya dan waktu tampil sebelum permintaan dikirim.
- Bahasa UI memakai istilah kerja, tanpa paragraf promosi atau penjelasan fitur yang tidak perlu.

### 8.3 Aksesibilitas

- Semua icon button memiliki `aria-label` dan tooltip.
- Navigasi dan diff dapat dipakai dengan keyboard.
- Warna bukan satu-satunya indikator status.
- Focus ring terlihat dan contrast minimal WCAG AA.
- Dialog mengunci fokus dan mengembalikannya ke pemicu saat ditutup.

## 9. Analisis Biaya OpenAI

### 9.1 Data aktual

Agregat produksi saat audit:

- 1.472 interaksi AI.
- 760.229 token input.
- 266.008 token output.
- 1.271 interaksi adalah embedding; sisanya mencakup mock, benchmark, dan percobaan model.
- Hanya satu sesi generator berstatus committed, sehingga data belum cukup untuk proyeksi statistik jangka panjang.

Baseline satu RPS committed untuk empat tahap awal:

- 13.269 token input.
- 7.433 token output.
- 5 CPMK, 10 Sub-CPMK, 16 minggu, 10 komponen.

Seluruh interaksi yang terikat ke sesi tersebut setelah retry/cache/pengujian mencapai sekitar 43.363 input dan 27.928 output. Angka ini dipakai sebagai skenario atas yang teramati, bukan kebutuhan ideal.

### 9.2 Harga standard per 1 juta token

| Model | Input | Cached input | Output |
| --- | ---: | ---: | ---: |
| `gpt-5.6-luna` | $0,20 | $0,02 | $1,20 |
| `gpt-5.6-terra` | $2,00 | $0,20 | $12,00 |
| `gpt-5.6-sol` | $4,00 | $0,40 | $20,00 |
| `text-embedding-3-small` | $0,02 | - | - |
| `text-embedding-3-large` | $0,13 | - | - |

Sumber: [OpenAI API Pricing](https://developers.openai.com/api/docs/pricing) dan [OpenAI Model Catalog](https://developers.openai.com/api/docs/models), diakses pada tanggal audit. Batch/Flex sekitar 50% dari harga standard untuk model yang didukung.

### 9.3 Estimasi biaya baseline

Rumus:

$$
\text{biaya} = \frac{T_{in}}{10^6}P_{in} + \frac{T_{out}}{10^6}P_{out}
$$

| Model | Satu RPS bersih | 100 RPS | 1.000 RPS |
| --- | ---: | ---: | ---: |
| Luna | $0,0116 | $1,16 | $11,57 |
| Terra | $0,1157 | $11,57 | $115,73 |
| Sol | $0,2017 | $20,17 | $201,74 |

Dengan asumsi kurs Rp16.000/USD, Luna sekitar **Rp185 per RPS bersih**, Terra Rp1.852, dan Sol Rp3.228. Pajak, data residency uplift, tool call, retry, serta token grounding belum dimasukkan.

### 9.4 Skenario atas berdasarkan sesi teramati

| Model | Satu RPS dengan retry/uji | 100 RPS | 1.000 RPS |
| --- | ---: | ---: | ---: |
| Luna | $0,0422 | $4,22 | $42,19 |
| Terra | $0,4219 | $42,19 | $421,86 |
| Sol | $0,7320 | $73,20 | $732,01 |

Selisih besar ini menunjukkan bahwa penghematan utama berasal dari selective regeneration, cache yang benar, pembatasan retry, dan routing model; bukan sekadar memilih model termurah.

### 9.5 Anggaran yang disarankan

- Default interaktif: Luna.
- Terra hanya untuk audit lintas tahap atau eskalasi quality gate.
- Sol memerlukan eskalasi otomatis atau pilihan pengguna berizin.
- Hard cap per RPS dan per pengguna; tampilkan sisa anggaran.
- Alarm pada lonjakan token, retry rate, fallback rate, atau rasio output/input.
- Nightly audit massal memakai Batch/Flex bila latensi tidak penting.
- Gunakan prompt caching untuk instruksi/acuan statis, tetapi ukur cache hit provider secara nyata.

## 10. Observability dan Tata Kelola AI

Setiap attempt minimal mencatat:

- tenant, actor, feature, task, session, item IDs;
- requested/effective provider dan model;
- prompt/schema/source version, tanpa menaruh secret;
- input, cached input, output, reasoning token bila tersedia;
- estimasi dan biaya provider;
- latency, status, error class, retry/fallback reason;
- candidate ID, applied operations, rejection reason;
- grounding coverage dan citation count.

Dashboard operasional harus menampilkan biaya per tenant/fitur/model, success rate, p50/p95 latency, retry/fallback, cache hit, grounding coverage, acceptance rate candidate, dan edit distance setelah apply.

## 11. Rencana Implementasi

### Fase 0 — Pengamanan (1–2 minggu)

- Lindungi route bisnis, policy, tenant scope, rate limit.
- Hilangkan `institusi_id = 1` dari jalur produksi.
- Perbaiki pricing model live dan audit fallback.
- Upgrade dependensi rentan dan perbaiki lint.
- Tambahkan test auth/tenant/cost yang memblokir deploy.

### Fase 1 — Fondasi granular (2–3 minggu)

- Tambahkan stable item IDs, revision, pin, dan metadata sumber.
- Implementasikan candidate/operations/apply/reject.
- Optimistic locking, audit log, revision restore.
- Migrasikan editor CPMK dan Sub-CPMK lebih dahulu.

### Fase 2 — UI workspace (2–3 minggu)

- Terapkan stepper, quality strip, editor tabel, panel AI, diff, dan impact review.
- Tambahkan bulk action dan mobile layout.
- Ganti dialog native dan standarkan loading/error/empty states.
- Playwright untuk alur utama dan konflik revisi.

### Fase 3 — Grounding dan biaya (2 minggu)

- Batch embedding/retrieval dan citation per item.
- Hard budget reservation dan rekonsiliasi provider.
- Routing Luna → Terra → Sol berbasis quality gate.
- Dashboard operasional AI.

### Fase 4 — Operasional produksi (1–2 minggu)

- CI quality gates, health/readiness, resource limits.
- Backup otomatis dan uji restore.
- Alerting, runbook incident/rollback, dan load test.

Estimasi total: **8–12 minggu** untuk tim kecil, tergantung kedalaman auth/SSO dan migrasi format draf lama. Fase 0 tidak boleh menunggu redesign UI.

## 12. Definition of Done

Perbaikan dianggap selesai bila:

- Semua endpoint nonpublik membutuhkan identity dan permission yang benar.
- Test lintas tenant membuktikan tidak ada baca/tulis silang.
- Tidak ada harga nol untuk model berbayar yang aktif.
- Pengguna dapat meminta revisi satu item/field tanpa kehilangan item lain.
- Pin tidak dapat dilewati oleh apply maupun bulk action.
- Candidate selalu memiliki diff, base revision, biaya, dan audit actor.
- Konflik revision menghasilkan 409 dan tidak menimpa perubahan terbaru.
- Grounding status dan citation terlihat per item; ketidakhadiran sumber tidak disamarkan.
- Backend test, frontend lint/build, Playwright smoke, dan audit dependensi menjadi gate CI.
- API/web memiliki healthcheck; backup pernah berhasil direstore dalam simulasi.
- Biaya internal dapat direkonsiliasi terhadap laporan provider dengan toleransi yang disepakati.

## 13. Keputusan Produk yang Direkomendasikan

1. Setujui pola **candidate patch + selective apply** sebagai kontrak AI baru.
2. Jadikan Luna model default, Terra validator/escalation, dan Sol pengecualian.
3. Kerjakan Fase 0 keamanan dan accounting sebelum memperluas pengguna produksi.
4. Gunakan prototipe ini sebagai baseline usability test dengan koordinator mata kuliah dan dosen.
5. Implementasikan CPMK/Sub-CPMK sebagai pilot sebelum membawa pola yang sama ke rencana mingguan dan penilaian.
