# Konsep Aplikasi: Curriculum Service — Generate RPS OBE Berbasis AI Co-Pilot

*Dokumen konsep — evolusi dari `Blueprint-Final-Lengkap.md` (mesin RPS/Kurikulum OBE). Aplikasi ini adalah **Curriculum Service**: sumber kebenaran kurikulum & RPS OBE. Dibangun **standalone dulu** (mandiri, punya master data sendiri), lalu **disesuaikan menjadi layanan di Academic Ecosystem** (master data pindah ke Academic Core). Kelak menjadi **sumber data RPS** yang dikonsumsi LMS.*

---

## 1. Visi & Positioning

**Visi:** Layanan yang menutup siklus OBE pada domain kurikulum — dari pemetaan CPL/kurikulum, generate RPS OBE berbantuan AI, validasi overlap, hingga evaluasi ketercapaian CPL — dengan **AI sebagai co-pilot** yang membantu dosen tanpa pernah menggantikan keputusan akademik manusia.

**Positioning (penting):** Ini **bukan** LMS dan **bukan** klon Moodle. Ini adalah **layanan kurikulum (Curriculum Service)** yang menghasilkan **RPS sebagai produk data**. Ketika LMS dibangun kelak, LMS **mengonsumsi RPS via API** dari service ini saat dosen "memilih kurikulum" — persis pola Academic Core sebagai sumber master data. Keunggulannya:
1. **OBE sebagai inti**, bukan tempelan — CPL/CPMK/Sub-CPMK terhubung end-to-end sampai pengukuran ketercapaian.
2. **AI co-pilot ber-traceability** — setiap bantuan AI dapat dilacak ke dokumen sumber (Panduan KPT, panduan asosiasi profesi, akreditasi).
3. **API-first** — kontrak RPS dirancang sejak awal agar LMS (dan konsumen lain) tinggal menempel.

**Hubungan antar layanan (target ekosistem):**
- **Academic Core** = sumber "siapa/apa" (prodi, MK, dosen, mahasiswa, kelas).
- **Curriculum Service (aplikasi ini)** = sumber "OBE-nya" (CPL/CPMK/RPS/capaian).
- **LMS (kelak)** = penyampaian pembelajaran; memilih kurikulum → menarik RPS dari Curriculum Service via API.

---

## 2. Strategi Dua Tahap: Standalone → Ekosistem

Aplikasi dibangun bertahap agar cepat dipakai sekarang, tapi mulus dimigrasikan nanti.

### Tahap 1 — Standalone (kebutuhan sekarang)
Aplikasi Kurikulum/RPS **mandiri penuh**, dijalankan sendiri (dari folder harness ini). Punya **master data sendiri**: `INSTITUSI`, `USER`, `MATA_KULIAH`, `DOSEN` disimpan lokal (diisi via upload/Column-Mapping seperti di blueprint). Tidak bergantung pada service lain — bisa langsung generate RPS.

### Tahap 2 — Layanan Ekosistem (nanti)
Aplikasi yang sama disesuaikan menjadi **Curriculum Service** resmi. Master data yang **sudah menjadi sumber kebenaran di Academic Core** (prodi, MK, dosen, mahasiswa, kelas) **dihapus dari lokal** dan **ditarik via API Academic Core**. Model & logika OBE (CPL/CPMK/RPS/capaian) tetap — tidak ditulis ulang.

### Kunci agar migrasi mulus (WAJIB diterapkan sejak Tahap 1)
1. **Referensi dengan kunci natural, bukan ID internal.** MK dirujuk via `kode_mk`, dosen via `NIDN`, mahasiswa via `NIM`. Saat sumber pindah ke Academic Core, kunci tetap cocok.
2. **`MasterDataProvider` (satu interface).** Semua akses master data lewat interface ini. Tahap 1 = `LocalMasterDataProvider` (baca tabel lokal); Tahap 2 = `AcademicCoreMasterDataProvider` (baca API Core). Yang berganti hanya implementasi, bukan model OBE.
3. **Pisahkan tegas** tabel "master data" (kandidat pindah ke Core) dari tabel "khas OBE" (selalu lokal). Jangan buat FK keras dari entitas OBE ke tabel master; gunakan kolom kunci natural.
4. **API-first sejak awal** — kontrak RPS publik dirancang di Tahap 1, tidak berubah di Tahap 2.

### Standar teknis (berlaku kedua tahap)
| Aspek | Keputusan |
|---|---|
| Backend | **Laravel API-only** (tanpa Filament) |
| Frontend | **Next.js** |
| Auth | **Keycloak (OIDC)** — boleh ditunda ke fase akhir seperti Survey |
| Master data | Tahap 1: lokal · Tahap 2: **konsumsi Academic Core** (via `MasterDataProvider`) |
| Database | **DB per service** — MySQL (selaras service lain: Academic Core, Survey) |
| Integrasi | via API/event, bukan shared DB |
| Primary key | **bigint auto-increment** semua tabel + kolom **`ulid`** unik selektif pada entitas yang dipertukarkan lintas-service (`KURIKULUM`, `MATA_KULIAH`, `RPS_VERSION`) — hemat, tetap aman untuk sync/merge |

**Entitas kandidat pindah ke Core (Tahap 2):** `INSTITUSI`→faculties/study_programs, `USER`→lecturers/staff, `MATA_KULIAH`→courses, `DOSEN`→lecturers. **Entitas selalu lokal:** CPL, Bahan Kajian, Keterampilan, CPMK, Sub-CPMK, RPS, capaian, dokumen rujukan, sitasi, konfigurasi aturan.

---

## 3. Prinsip Desain (mengikat)

Mewarisi blueprint, ditambah prinsip untuk lapisan LMS & co-pilot:

1. **Configuration over code** — perbedaan antar tenant/fakultas adalah data, bukan kode.
2. **AI menginterpretasi makna, kode menstruktur data** — tidak pernah dibalik.
3. **Human-in-the-loop untuk keputusan kritikal** — approval RPS, nilai akhir, dan fakta kaku (jumlah minggu, bobot) selalu dikonfirmasi manusia.
4. **Traceability wajib** — tiap klaim/saran AI dapat dilacak ke dokumen + halaman sumber.
5. **Siklus tertutup** — OBC → OBLT → OBAEI; data capaian nyata mengalir kembali ke perbaikan RPS.
6. **AI adalah asisten, bukan pengambil keputusan** — co-pilot menyarankan, dosen memutuskan; semua saran dapat diterima/ditolak/disunting.
7. **Privasi data mahasiswa** — data capaian/PII tidak dikirim ke penyedia LLM tanpa kebijakan jelas; kendali biaya & isolasi via per-user/per-tenant API key.

---

## 4. Konsep "AI Co-Pilot" — Konkret

AI hadir dalam **enam mode**, bukan sekadar generator batch:

| Mode | Contoh | Guardrail |
|---|---|---|
| **Generatif** | Draf CPMK/Sub-CPMK/16 minggu/rubrik dari CPL + RAG dokumen asli | Traceability per klaim; dosen menyunting |
| **Asistif inline** | Saran perbaikan kalimat CPMK, deteksi kata kerja tak terukur (cek wordlist `TAKSONOMI` dulu, LLM untuk kasus ambigu), peringatan CPMK tanpa asesmen | Non-blocking; hanya saran |
| **Konversasional (chat-with-curriculum)** | "Bahan kajian mana yang belum tercakup MK apa pun?", "Tunjukkan CPMK yang tidak selaras dengan rubriknya" | Jawaban ber-sitasi ke data/dokumen |
| **Penyelaras acuan** | Cocokkan PL/CPL/bahan kajian yang di-upload ke butir kerangka acuan (APTFI/LAM/KPT); tandai butir wajib yang belum terpenuhi + rekomendasi | Hasil = ceklist ber-status; dikonfirmasi manusia |
| **Validator anti-halusinasi** | Setelah generate: ekstrak klaim dari output → cek tiap klaim terhadap chunk RAG & data kurikulum → skor grounding → klaim tak didukung ditandai + **konteks yang benar dikembalikan** (regenerasi otomatis / review manual) | Berjalan otomatis sebelum draf disajikan; hasil dicatat di `AI_VALIDASI`; klaim tak didukung tidak pernah tampil tanpa tanda |
| **Evaluatif (QA/judge)** | Skor mutu draf RPS terhadap rubrik institusi; ringkasan naratif evaluasi CPL | Skor = indikator, bukan keputusan; divalidasi Kaprodi/GPM |

> **Aset yang dipakai ulang dari benchmark-harness:** abstraksi driver multi-model → `AiService`; mekanisme *judge* → mode **Evaluatif**; sistem task+rubric → **template prompt generasi + rubrik mutu**; per-user API key → kendali biaya per dosen/tenant.

---

## 5. Peta Modul

**Bagian A (Modul 0–8) = cakupan Curriculum Service ini.** Bagian B (Modul 9–12) = LMS Service terpisah kelak, hanya untuk konteks.

### Bagian A — Curriculum Service / Otak OBE (aplikasi ini)
| # | Modul | Fungsi Inti | Fase |
|---|---|---|---|
| 0 | **Onboarding & Ingestion** | Upload CPL/Bahan Kajian/MK (Column-Mapping UI) + dokumen rujukan (parse Python → index vektor per-tenant) | MVP |
| 0b | **Penyelarasan Acuan (Checklist)** | Kerangka acuan generik (KPT/SN-Dikti, APTFI, LAM-PTKes, BAN-PT, dll) berisi butir yang **diceklist**; saat upload PL/CPL, AI mencocokkan ke butir, tandai yang belum terpenuhi, beri rekomendasi selaras | MVP |
| 1 | **Peta Kurikulum** | Matriks CPL×Bahan Kajian, CPL×MK, PL×CPL, CPMK×CPL | MVP |
| 2 | **RPS Generator** | AI generate CPMK/Sub-CPMK/minggu/rubrik + RAG + traceability — pipeline bertahap dgn checkpoint (lihat §7c) | MVP |
| 2b | **RPS Audit & Konversi** *(opsional)* | Impor RPS lama (Word/Excel) → parse → gap analysis OBE → tawaran upgrade masuk pipeline sebagai draf awal. **Jalur masuk opsional**: tanpa RPS lama, generator berjalan murni dari CPL/BK — hasil tidak terpengaruh | Fase 2 |
| 3 | **Validator Overlap** | Deteksi bahan kajian diklaim >1 MK + rekomendasi | Fase 2 |
| 4 | **Workflow Approval** | Dosen → Kaprodi/GPM, versioning, audit trail | Fase 2 |
| 5 | **Export & Arsip** | Render DOCX/PDF sesuai template tenant | Fase 3 |
| 6 | **OBAEI** | Capaian mahasiswa → agregasi CPL → tindak lanjut | Fase lanjut |
| 7 | **Traceability Layer** | Sitasi sumber pada tiap output AI (cross-cutting) | MVP |
| 8 | **Governance & Monitoring** | Usage/biaya per tenant, versi pedoman, audit viewer | Scale-up |

### Bagian B — Lapisan Penyampaian LMS (SERVICE TERPISAH, kelak)
> Modul 9–12 **bukan bagian dari Curriculum Service ini**. Ini milik **LMS Service** terpisah yang dibangun kemudian, dan **mengonsumsi RPS dari Curriculum Service via API**. Dicantumkan di sini hanya untuk gambaran arah ekosistem.

| # | Modul | Fungsi Inti | Fase |
|---|---|---|---|
| 9 | **Kelas & Enrolmen** | Sinkron kelas/rombel & peserta dari Academic Core; ruang kelas per MK per semester | LMS Service |
| 10 | **Materi & Aktivitas** | Unggah materi, aktivitas mingguan **terhubung Sub-CPMK** (dari RPS Curriculum Service) | LMS Service |
| 11 | **Asesmen & Gradebook OBE** | Tugas/kuis/rubrik yang **memetakan skor ke Sub-CPMK** → capaian dikirim balik ke Curriculum Service (OBAEI) | LMS Service |
| 12 | **Co-Pilot Konversasional** | Asisten chat untuk dosen (susun materi/soal) & (opsional) feedback formatif mahasiswa | LMS Service |

**Kunci integrasi A↔B (lintas service):** saat LMS "memilih kurikulum", ia menarik RPS (Sub-CPMK, rubrik, rencana mingguan) dari **API Curriculum Service**. Hasil penilaian nyata dikirim balik sebagai `CAPAIAN_MAHASISWA` untuk menutup siklus OBAEI — tanpa input manual, tanpa duplikasi model OBE.

---

## 6. Arsitektur Teknis

```
┌────────────────────────────────────────────────────────────┐
│                    Next.js (Frontend)                       │
│   Wizard onboarding · Builder RPS · Gradebook · Co-Pilot    │
└───────────────┬────────────────────────────────────────────┘
                │ REST (Bearer/Keycloak)
┌───────────────▼────────────────────────────────────────────┐
│                 Laravel API-only (Backend inti)             │
│  Auth · CRUD · Workflow · Queue/Horizon · AiService         │
│  ├─ AiService (driver multi-provider: Claude/OpenAI/…)      │
│  ├─ RAG orchestrator (retrieve → prompt → cite)             │
│  └─ Gradebook→SubCPMK mapper                                │
└───┬───────────────┬───────────────────────┬────────────────┘
    │               │                       │
    │ REST          │ REST (jarang)         │ HTTP
┌───▼─────────┐ ┌───▼──────────────┐  ┌─────▼──────────────┐
│ Academic    │ │ Python Doc-Intel │  │ LLM Providers      │
│ Core (API)  │ │ (parse PDF/DOCX, │  │ (Claude/OpenAI/…)  │
│ master data │ │  stateless)      │  │ per-user API key   │
└─────────────┘ └───┬──────────────┘  └────────────────────┘
                    │ teks + metadata
              ┌─────▼──────────────┐
              │   MySQL 8         │
              │ (DB service ini) │
              │ embedding di JSON │
              └────────────────────┘
```

**Keputusan stack:**
- **Laravel** = 80% aplikasi (auth, CRUD, workflow, queue).
- **Python microservice** = HANYA parsing dokumen (PDF/DOCX → teks + halaman). Tipis, stateless, dipanggil saat upload (jarang). Tidak ada distilasi/ringkasan AI atas dokumen otoritatif.
- **MySQL 8** = DB service ini (selaras Academic Core & Survey; aturan DB-per-service). **RAG tanpa pgvector:** chunk dokumen + embedding disimpan sebagai kolom JSON; retrieval memakai **cosine similarity dihitung di aplikasi** (jumlah chunk per-tenant kecil — beberapa dokumen pedoman). Isolasi per-tenant via kolom `institusi_id`. Bila skala membesar, embedding bisa dipindah ke vector store eksternal tanpa mengubah model relasional.
- **Redis + Horizon** = antrian generate massal & panggilan AI panjang agar UI responsif.
- **Object storage (S3-compatible)** = arsip RPS DOCX/PDF & materi LMS, terpisah per tenant.

---

## 7. Strategi AI

| Aspek | Pendekatan |
|---|---|
| Sumber aturan (KPT, asosiasi, akreditasi) | Parse ke teks asli via Python → index vektor. **Tanpa** distilasi AI. |
| Interpretasi saat generate | AI real-time + RAG merujuk teks asli. |
| Fakta kaku (16 minggu, bobot 100%) | Diisi manusia via `KONFIGURASI_ATURAN`; opsional sanity-check regex. |
| Traceability | Tiap klaim menyimpan `SOURCE_CITATION` (dokumen + halaman + cuplikan). |
| Multi-provider | Abstraksi driver (warisan harness) — institusi pilih model, bandingkan, kendalikan biaya. |
| QA mutu (judge) | Model penilai memberi skor+alasan terhadap rubrik; **human validasi**. |
| **Anti-halusinasi (grounding)** | Pipeline generate → validasi: (1) ekstrak klaim atomik dari output; (2) tiap klaim dicari dukungannya di `DOKUMEN_CHUNK` (embedding) + data kurikulum tenant; (3) skor grounding 0–1 per klaim; (4) klaim `tidak_didukung` → validator **mengembalikan konteks yang benar** dan memicu regenerasi otomatis (maks. 1×) atau tanda "perlu review" di UI. Semua tercatat di `AI_VALIDASI`. Model validator boleh berbeda dari model generator (silang-provider mengurangi bias). |
| Jejak & biaya AI | Setiap panggilan dicatat di `AI_INTERAKSI` (mode, model, prompt, output, token, biaya, status diterima/disunting/ditolak) — untuk audit akreditasi & kendali biaya. Kunci API per-tenant/per-user di `AI_KREDENSIAL` (terenkripsi). |
| Efisiensi biaya | Prompt caching konteks per-tenant; Batch API untuk generate massal. |
| Privasi | Data capaian/PII tidak dikirim ke LLM tanpa kebijakan; per-user/tenant key untuk isolasi & kuota. |

### 7b. Pembeda Kaku: MK Murni (Teori) vs MK Praktikum

Beberapa tenant memisahkan MK teori dan praktikum secara kaku — keterampilan yang diperoleh/diukur berbeda. Karena itu `MATA_KULIAH.jenis_mk` (`murni` / `praktikum`) menjadi **sumbu pembeda** yang mengubah perilaku seluruh alur RPS:

| Dimensi | MK Murni (teori) | MK Praktikum |
|---|---|---|
| **SKS → jam (otomatis)** | 1 SKS/minggu = 50' tatap muka + 60' penugasan terstruktur + 60' mandiri (total 170') | 1 SKS/minggu = 170' praktik (lab/studio/lapangan) |
| **Domain taksonomi dominan** | Kognitif — Bloom-Anderson C1–C6 (mengingat → mencipta) | Psikomotorik — Dave/Simpson P1–P7 (imitasi → naturalisasi) + Afektif — Krathwohl A1–A5 (menerima → karakterisasi) |
| **Sub-CPMK** | `taksonomi_id` difilter ke domain kognitif | Difilter ke psikomotorik + afektif (kognitif pendukung diperbolehkan) |
| **Komponen penilaian** | Tugas / kuis / UTS / UAS | Laporan praktikum / responsi / skill assessment / OSCE / lembar observasi |
| **Instrumen** | Rubrik analitik/holistik | Checklist keterampilan, lembar observasi afektif |
| **Estimasi waktu RPS mingguan** | `estimasi_waktu` = TM/PT/KM (menit), dihitung otomatis dari `sks_teori` | `estimasi_waktu` = menit praktik dari `sks_praktik` |
| **Prompt AI generate** | Template teori (penekanan penguasaan konsep, kata kerja C) | Template praktikum (penekanan prosedur, keselamatan kerja, kata kerja P/A) |

**Mekanisme pendukung:**
- **`TAKSONOMI` (master, seed global + bisa ditambah per-tenant):** domain (kognitif/afektif/psikomotorik) × kerangka (Bloom-Anderson, Krathwohl, Dave/Simpson) × level (C1..C6, A1..A5, P1..P7) + **daftar kata kerja operasional terukur** per level. Dipakai untuk: dropdown level di builder, guardrail deterministik "kata kerja tak terukur", dan konteks prompt AI.
- **Konversi SKS→jam dikonfigurasi** di `KONFIGURASI_ATURAN` (`jenis_aturan=konversi_sks`) — default SN-Dikti di atas, tenant boleh override. UI pemilihan SKS langsung menampilkan rincian jam/menit per minggu & per semester (×16 minggu).
- **MK campuran** (sks_teori>0 dan sks_praktik>0) tetap didukung: `jenis_mk` menentukan mode dominan tampilan/prompt, estimasi waktu dihitung dari kedua komponen.

### 7c. Desain Inti Generator RPS (Modul 2) — KEPUTUSAN FINAL

**1) Pipeline bertahap dengan checkpoint manusia (bukan one-shot):**

```
CPMK → [review dosen] → Sub-CPMK + Indikator → [review] → 16 Minggu + Estimasi Waktu → [review] → Komponen Penilaian + Rubrik → [review] → Commit
```
- Output tahap sebelumnya (yang sudah disunting manusia) menjadi input tahap berikutnya.
- **Regenerasi parsial**: regenerate satu minggu / satu CPMK saja tanpa merombak lainnya.
- **Pin/lock**: bagian yang sudah disunting dosen dikunci agar tidak tertimpa regenerasi.
- Tiap tahap melewati **validator anti-halusinasi** sebelum disajikan.

**2) Staging — draf AI terpisah dari data resmi (`GENERATE_SESSION`):**
- Draf terstruktur (JSON) per tahap + status per bagian (`diterima/disunting/ditolak/pinned`) disimpan di `GENERATE_SESSION`, terhubung ke `AI_INTERAKSI` & `AI_VALIDASI`.
- Baru saat dosen menekan **Commit**, draf ditulis menjadi baris resmi `CPMK`/`SUB_CPMK`/`RPS_MINGGU`/`KOMPONEN_PENILAIAN`.
- `sumber` sesi: `baru` (dari CPL/BK), `impor_rps_lama` (Modul 2b, opsional), `copy_tahun_lalu` (reuse).

**3) Template prompt + output ber-skema (`PROMPT_TEMPLATE`):**
- Kombinasi `jenis_output` (cpmk/sub_cpmk/mingguan/rubrik/audit) × `jenis_mk` (murni/praktikum); per-tenant, versioned, ada default global.
- Konteks yang disuntikkan: CPL terkait + bobot, bahan kajian, level `TAKSONOMI` yang diizinkan + kata kerjanya, `KONFIGURASI_ATURAN`, chunk RAG, **RPS MK prasyarat**, keterampilan yang sudah diklaim MK lain.
- Output AI **wajib JSON ber-skema** (structured output) agar terparse langsung ke entitas — bukan teks bebas.
- Few-shot diambil dari draf yang "diterima utuh" oleh dosen (feedback loop dari `AI_INTERAKSI.status`).

**4) Rule engine kelengkapan — deterministik, TANPA AI ("skor kesehatan RPS"):**
Berjalan sebelum RPS bisa diajukan approve; hasilnya health-score di builder:
- Σ bobot CPMK = 100% · Σ bobot komponen penilaian = 100% · Σ bobot minggu = 100%
- Semua minggu terisi (jumlah dari `KONFIGURASI_ATURAN`); UTS/UAS ada
- Tiap CPMK ≥1 Sub-CPMK & ≥1 CPL (via `CPMK_CPL`); tiap Sub-CPMK ≥1 indikator, ≥1 minggu, ≥1 komponen penilaian
- Kata kerja Sub-CPMK ∈ `TAKSONOMI.kata_kerja` sesuai domain `jenis_mk`
- Σ `estimasi_waktu` mingguan = jam hasil konversi SKS (§7b)

**5) Konteks lintas-MK saat generate:** RPS MK prasyarat (hindari duplikasi materi, tentukan entry point) + klaim `MK_KETERAMPILAN` MK lain (hindari overlap sejak generate, bukan hanya terdeteksi belakangan di Modul 3).

**6) Reuse tahunan & menutup loop OBAEI:** copy RPS tahun lalu → draf `tahun_kurikulum` baru; AI menyuntikkan `VERSI_PEDOMAN.mk_terdampak` (pedoman baru) + `TINDAK_LANJUT` hasil evaluasi CPL → evaluasi tahun lalu benar-benar mengubah RPS tahun depan.

**7) Generate massal per prodi (Fase 2):** Kaprodi pilih semua MK semester X → antrian Horizon → progress per MK → hasil masuk `GENERATE_SESSION` masing-masing → dosen pengampu me-review miliknya.

**8) Rubrik sebagai entitas (`RUBRIK` + `RUBRIK_KRITERIA`):** rubrik analitik = kriteria × bobot × deskriptor per level skala; melekat pada `KOMPONEN_PENILAIAN`; jenis menyesuaikan `jenis_mk` (analitik/holistik untuk teori; checklist skill/lembar observasi untuk praktikum). Inilah artefak yang kelak dikonsumsi LMS untuk menilai.

### 7d. Peran AI per Field & Routing Model — KEPUTUSAN FINAL

Setiap field diklasifikasi sumber pengisinya. Klasifikasi ini menentukan **model apa yang dipakai**, **temperature**, dan **bentuk kode** (`AiService` routing per-task).

**Legenda sumber:**
| Kode | Sumber | Karakter tugas | Kelas model |
|---|---|---|---|
| **M** | Manusia | Input/keputusan manual | — |
| **D** | Deterministik | Dihitung kode (SQL/rumus/parser) | — (tanpa AI) |
| **G1** | AI Generatif berat | Penalaran pedagogi, menyusun konten baru | **Frontier** (Claude Sonnet / GPT-4o) · temp 0.5–0.7 |
| **E2** | AI Ekstraksi/Klasifikasi | Memetakan/mengekstrak/mengklasifikasi teks yang SUDAH ADA | **Ringan-murah** (Haiku / GPT-4o-mini / Gemini Flash) · temp 0–0.2 · JSON schema |
| **V2** | AI Validator (anti-halu) | Cek klaim vs bukti (NLI/groundedness) | Ringan-murah, **provider ≠ generator** · temp 0 |
| **J1** | AI Judge | Menilai mutu vs rubrik + alasan | Frontier · temp 0 |
| **EMB** | Embedding | Vektorisasi & kemiripan | `text-embedding-3-small` / `bge-m3` (Ollama) |

**Matriks per entitas (hanya field bermakna; field `id`/`institusi_id`/timestamp selalu D):**

| Entitas.Field | Sumber | Catatan |
|---|---|---|
| `MATA_KULIAH.*` (kode, nama, sks, jenis_mk, sifat, rumpun, semester) | **M** | Master data — upload/isian; AI tidak menyentuh |
| `MATA_KULIAH.deskripsi_singkat` | M / **G1** saran | AI boleh draf, dosen finalisasi |
| `CPL.deskripsi`, `PROFIL_LULUSAN.deskripsi` | **M** (upload) | Otoritatif dari prodi/asosiasi — AI DILARANG mengarang |
| `CPL.aspek`, `CPL.level_kkni` | **E2** → M konfirmasi | Klasifikasi teks CPL yang di-upload |
| `COLUMN_MAPPING.mapping` | **E2** → M konfirmasi | Tebak kolom Excel ("kolom C = kode_mk") |
| `BAHAN_KAJIAN.*`, `KETERAMPILAN.deskripsi` | **M** (upload) | Otoritatif |
| `KETERAMPILAN.domain`, `.taksonomi_id` | **E2** → M | Klasifikasi C/A/P dari deskripsi |
| `PL_CPL`, `MK_CPL` (relasi) | M / **E2** saran | AI menyarankan pemetaan, manusia centang |
| `MK_CPL.bobot`, `CPMK_CPL.bobot` | **G1** usul → M | Angka bobot = keputusan kurikuler manusia |
| `CPMK.kode`, `SUB_CPMK.kode` | **D** | Auto-sequence (CPMK-1, Sub-CPMK-1.1) |
| `CPMK.deskripsi`, `CPMK.bobot_persen` | **G1** → M sunting | Inti generator; RAG + konteks kurikulum |
| `SUB_CPMK.deskripsi` | **G1** → M | Kata kerja WAJIB dari whitelist `TAKSONOMI` (constraint di prompt + dicek D) |
| `SUB_CPMK.taksonomi_id` | **G1** (constrained) → **D** verifikasi | AI memilih dari daftar yang diizinkan `jenis_mk`; kode menolak yang di luar |
| `SUB_CPMK.minggu_*`, `.bobot_persen` | **G1** → M | |
| `INDIKATOR.deskripsi` | **G1** → M | |
| `RPS_MINGGU.indikator`, `.teknik_kriteria_penilaian`, `.bentuk_luring/daring`, `.pengalaman_belajar` | **G1** → M | |
| `RPS_MINGGU.metode_pembelajaran` | **G1** dari enum | Pilih dari daftar metode terkontrol (bukan teks bebas) |
| `RPS_MINGGU.materi_pustaka` | **G1** + RAG | WAJIB ber-`SOURCE_CITATION`; klaim pustaka dicek V2 |
| `RPS_MINGGU.estimasi_waktu` | **D** | RUMUS dari SKS (§7b) — AI dilarang mengisi |
| `RPS_MINGGU.minggu_ke`, `.bobot_penilaian` | D / **G1** → M | |
| `KOMPONEN_PENILAIAN.nama/jenis/minggu_ke/bobot_persen` | **G1** (enum sesuai jenis_mk) → M | Teori: tugas/kuis/UTS/UAS; praktikum: laporan/OSCE/skill |
| `RUBRIK.*`, `RUBRIK_KRITERIA.*` (kriteria, deskriptor per level) | **G1** → M | Deskriptor bergradasi = tugas penalaran halus, wajib frontier |
| `REFERENSI.sitasi` | M / **G1**+RAG | Hanya dari pustaka yang terverifikasi ada (anti-halu ketat) |
| `TEMPLATE_RPS.struktur_kolom` | **E2** → M konfirmasi | Deteksi struktur dari contoh template tenant |
| `KONFIGURASI_ATURAN.nilai` | **M** murni | Keputusan final lama: aturan otoritatif diisi manusia |
| `DOKUMEN_CHUNK.teks/halaman/token_count` | **D** (Python parser) | Bukan AI |
| `DOKUMEN_CHUNK.embedding` | **EMB** | |
| `BUTIR_ACUAN.*` (kode, deskripsi, hierarki, kategori) | **E2** → M review | Ekstraksi butir dari dokumen acuan (APTFI/LAM/KPT) |
| `PEMENUHAN_ACUAN.status` | **EMB** kandidat + **V2** verifikasi → M konfirmasi | Ceklist tidak pernah final tanpa manusia |
| `PEMENUHAN_ACUAN.catatan` (rekomendasi) | **G1** | Saran penyelarasan |
| `VALIDASI_OVERLAP.mk_terlibat` | **EMB** + D threshold | Kemiripan vektor antar klaim |
| `VALIDASI_OVERLAP.analisis/rekomendasi` | **G1** | Narasi penjelasan |
| `SOURCE_CITATION.*` | **D** | Efek samping RAG — chunk yang benar-benar dipakai |
| `AI_VALIDASI.klaim` | **E2** | Ekstraksi klaim atomik dari output G1 |
| `AI_VALIDASI.status/skor_grounding/konteks_pengganti` | **V2** (+EMB retrieve) | Provider berbeda dari generator |
| `AI_VALIDASI.tindakan` | **D** (policy) | Aturan: skor<ambang → regenerasi 1× → tandai review |
| `CAPAIAN_MAHASISWA.*` | **D** | Agregasi statistik murni |
| `EVALUASI_CPL.ringkasan_naratif` | **G1** dari angka D → M finalisasi | Angka dihitung dulu, AI hanya menarasikan |
| `TINDAK_LANJUT.catatan` | **G1** saran → M | |
| `GENERATE_SESSION.draf` | **G1** | `status_bagian` = M |
| `PROMPT_TEMPLATE.sistem_prompt/skema_output` | M (dev/admin) | `few_shot` = **D** (otomatis dari draf diterima utuh) |
| Skor mutu RPS (judge) | **J1** | Indikator, bukan keputusan |
| Chat-with-curriculum | **G1**/mid + RAG | Read-only, ber-sitasi |

**Implikasi ke kode (`AiService`) — pola routing per-task:**

```php
// config/ai.php — tabel routing: SATU sumber kebenaran
'tasks' => [
  'generate_cpmk'      => ['tier'=>'frontier', 'temp'=>0.6, 'schema'=>CpmkDraftSchema::class],
  'generate_mingguan'  => ['tier'=>'frontier', 'temp'=>0.6, 'schema'=>WeeklyPlanSchema::class],
  'generate_rubrik'    => ['tier'=>'frontier', 'temp'=>0.5, 'schema'=>RubrikSchema::class],
  'audit_rps_lama'     => ['tier'=>'frontier', 'temp'=>0.3, 'schema'=>AuditSchema::class],
  'classify'           => ['tier'=>'light',    'temp'=>0.0, 'schema'=>...], // aspek/domain/kategori
  'map_column'         => ['tier'=>'light',    'temp'=>0.0, ...],
  'extract_butir'      => ['tier'=>'light',    'temp'=>0.0, ...],
  'extract_claims'     => ['tier'=>'light',    'temp'=>0.0, ...],
  'validate_grounding' => ['tier'=>'light',    'temp'=>0.0, 'cross_provider'=>true],
  'judge_rps'          => ['tier'=>'frontier', 'temp'=>0.0, ...],
  'narrate_evaluasi'   => ['tier'=>'frontier', 'temp'=>0.4, ...],
  'chat'               => ['tier'=>'frontier', 'temp'=>0.3], // tanpa schema
  'embed'              => ['tier'=>'embedding'],
],
```

- **Tier → model** dipetakan per-tenant di `AI_KREDENSIAL`/konfigurasi (frontier: Claude Sonnet/GPT-4o; light: Haiku/GPT-4o-mini/Flash; embedding: text-embedding-3-small/bge-m3). Tenant ganti provider **tanpa ubah kode**.
- **Semua task ber-skema** memakai structured output; parse gagal = retry 1× lalu error eksplisit (tidak pernah "kira-kira").
- **`validate_grounding` wajib `cross_provider: true`** — validator tidak boleh satu provider dengan generator (mengurangi bias sekawan).
- Setiap panggilan dicatat `AI_INTERAKSI` dengan nama task → dashboard biaya per-task per-tenant; task `light` diproyeksikan ~80% volume panggilan dengan <20% biaya.
- Konsekuensi ekonomi: hanya 5 task frontier (generate×4 + judge + narasi); sisanya murah/lokal.


---

## 8. Model Data (ringkas)

Mewarisi `9-ERD-master.mermaid`. Model dikelompokkan menurut strategi dua tahap:

**Master data (Tahap 1 lokal → Tahap 2 pindah ke Academic Core):**
- `INSTITUSI`, `USER`/`DOSEN`, `MATA_KULIAH` — di Tahap 2 hanya menyimpan `external_id` + kunci natural (`kode_mk/NIDN/NIM`) + cache seperlunya. Diakses lewat `MasterDataProvider`.

**Khas OBE (selalu lokal, tidak pernah pindah):**
- **Payung versi:** `KURIKULUM` (institusi_id, tahun, status draft/berlaku/arsip, `mengganti_id` self-FK = garis revisi). Menjadi sumbu versi: `PROFIL_LULUSAN`, `CPL`, `BAHAN_KAJIAN`, `MATA_KULIAH` semua ber-`kurikulum_id`. Dua kurikulum boleh paralel (angkatan lama arsip + baru berlaku). Uniqueness kode jadi per-kurikulum.
- **Inti kurikulum:** `PROFIL_LULUSAN`, `CPL` (aspek SN-Dikti + KKNI), `TARGET_CPL` (ambang + %% target per angkatan — kalibrasi OBAEI), `PL_CPL`, `BAHAN_KAJIAN`, `KETERAMPILAN` (+domain C/A/P), `CPMK`, `CPMK_CPL`, `SUB_CPMK` (+`taksonomi_id`), `INDIKATOR`, `TAKSONOMI` (master level C/A/P + kata kerja operasional).
- **Kepemilikan MK:** `MK_PENGAMPU` (kode_mk × dosen_nidn × peran koordinator/anggota) menggantikan string `dosen_pengampu` — basis otorisasi "dosen hanya MK sendiri".
- **RPS & versioning:** `RPS_VERSION` (dosen pengembang + koordinator MK + kaprodi), `RPS_MINGGU` (+metode pembelajaran, estimasi waktu otomatis dari SKS), `KOMPONEN_PENILAIAN` (rencana asesmen → Sub-CPMK; jenis berbeda teori vs praktikum), `RUBRIK` + `RUBRIK_KRITERIA` (kriteria × bobot × deskriptor), `REFERENSI`.
- **Generator:** `GENERATE_SESSION` (staging draf per tahap + status per bagian → commit ke entitas resmi), `PROMPT_TEMPLATE` (per jenis_output × jenis_mk, versioned, skema output JSON + few-shot).
- **Onboarding & aturan:** `DOKUMEN_RUJUKAN`, `DOKUMEN_CHUNK` (potongan teks + embedding JSON untuk RAG), `VERSI_PEDOMAN`, `TEMPLATE_RPS`, `KONFIGURASI_ATURAN` (termasuk konversi SKS→jam), `COLUMN_MAPPING`.
- **Kerangka acuan (checklist, lintas otoritas):** `BADAN_RUJUKAN` (APTFI/LAM-PTKes/KPT/BAN-PT/…), `KERANGKA_ACUAN`, `BUTIR_ACUAN` (butir yang diceklist, hierarkis), `PEMENUHAN_ACUAN` (status pemenuhan + rekomendasi AI, polimorfik ke PL/CPL/dst).
- **Validasi & traceability:** `VALIDASI_OVERLAP`, `SOURCE_CITATION`.
- **Lapisan AI:** `AI_KREDENSIAL` (API key terenkripsi per-tenant/user), `AI_INTERAKSI` (log semua panggilan: mode/model/token/biaya/status), `AI_VALIDASI` (hasil validator anti-halusinasi per klaim: status didukung/tidak, bukti chunk, skor grounding, konteks pengganti).
- **OBAEI:** `CAPAIAN_MAHASISWA`, `EVALUASI_CPL`, `TINDAK_LANJUT`.
- **Governance:** `NOTIFIKASI`, `AUDIT_LOG`.

> Entitas OBE merujuk MK/dosen lewat **kunci natural** (`kode_mk`, `NIDN`), bukan FK keras ke tabel master — inilah yang membuat perpindahan sumber master data di Tahap 2 tidak merusak relasi OBE.
> Tabel penyampaian LMS (`KELAS_LMS`, `AKTIVITAS`, `ASESMEN`, `SUBMISSION`, `NILAI`) **tidak ada di service ini** — milik LMS Service terpisah.

---

## 9. Keamanan & Multi-Tenancy

- Setiap tabel inti punya `institusi_id` (isolasi ketat antar fakultas/prodi).
- RBAC (via Keycloak): `dosen` (MK sendiri) / `kaprodi`/`gpm` (lintas MK prodi) / `admin_pusat` (lintas institusi).
- Vektor dipartisi per-tenant namespace — dokumen 1 fakultas tak bocor ke RAG fakultas lain.
- Audit log lengkap untuk akreditasi (siapa ubah apa, kapan) + jejak setiap interaksi AI (prompt, model, output, penyetuju).
- Guardrail OWASP: whitelist kolom sort, validasi input di boundary, tanpa shared DB.

---

## 10. Roadmap Implementasi

> **Tahap 1 (Standalone)** = seluruh fase MVP–Fase 4 dijalankan dengan master data lokal. **Tahap 2 (Ekosistem)** = ganti `MasterDataProvider` ke Academic Core + wiring Keycloak, tanpa menulis ulang model OBE.

| Fase | Cakupan | Validasi Keberhasilan |
|---|---|---|
| **MVP (Otak OBE)** | Modul 0,1,2,7 — pilot 3–5 MK Farmasi UMI (master data lokal) | Draf RPS akurat, sitasi sumber berfungsi |
| **Fase 2** | Modul 3 (Overlap), 4 (Workflow) | Kasus overlap (mis. "Pengecilan Ukuran Partikel") terdeteksi otomatis |
| **Fase 3** | Modul 5 (Export), scale 100 MK | Batch generate 100 MK sukses, biaya sesuai estimasi |
| **Fase 4 — Uji Generalisasi** | Onboarding tenant kedua | **Tanpa perubahan kode** — jika gagal, arsitektur multi-tenant direvisi |
| **→ Migrasi ke Ekosistem (Tahap 2)** | Ganti `MasterDataProvider` ke Academic Core, wiring Keycloak, ekspos API RPS publik | Master data ditarik dari Core, model OBE tak berubah, RPS bisa dibaca konsumen luar |
| **Fase 5 — OBAEI penuh** | Modul 6 dengan data nilai nyata | Ketercapaian CPL teragregasi setelah ≥1 siklus semester |
| **Fase 6 — Scale-up** | Modul 8 (Governance), rollout universitas | Dashboard admin pusat aktif, multi-fakultas paralel |
| **(Service lain) LMS** | Modul 9–12 di **LMS Service** terpisah | LMS "pilih kurikulum" → tarik RPS dari API Curriculum Service; kirim balik capaian |

**Prinsip fasing:** bangun **Curriculum Service standalone** dulu (nilai unik & risiko rendah, langsung dipakai), selesaikan otak OBE, baru migrasi ke ekosistem dengan mengganti sumber master data. LMS adalah service terpisah yang menyusul — bukan bagian dari aplikasi ini.

---

## 11. Jalur Migrasi dari `benchmark-harness`

Aset yang sudah jadi dan langsung dipakai ulang:

| Aset di harness | Menjadi | Catatan |
|---|---|---|
| Abstraksi driver LLM (`BenchmarkRunner` + driver per provider) | `AiService` bersama | Pertahankan pola multi-provider |
| Per-user API key (localStorage + override backend) | Kendali biaya per dosen/tenant | Untuk produksi multi-user, pindah ke penyimpanan per-tenant terenkripsi |
| Mekanisme *judge* | Mode Evaluatif (QA mutu RPS) | Skor = indikator, bukan keputusan |
| Sistem task + rubric | Template prompt generasi + rubrik mutu | Jadi konfigurasi per-tenant |
| SQLite (demo/LAN) | MySQL (produksi) | Untuk beban multi-user |

---

## 12. Keputusan yang Perlu Ditegaskan

| # | Keputusan | Status |
|---|---|---|
| 1 | **Strategi dua tahap** — bangun Curriculum Service standalone dulu, lalu migrasi ke ekosistem (master data pindah ke Academic Core via `MasterDataProvider`). | ✅ **Disepakati** |
| 2 | **LMS = service terpisah** — aplikasi ini fokus kurikulum/RPS; LMS menyusul & mengonsumsi RPS via API. | ✅ **Disepakati** |
| 3 | **Ruang lingkup target awal** — Farmasi UMI dulu, arsitektur tetap **multi-tenant** untuk skala universitas. | ✅ **Disepakati** (multi-tenant) |
| 4 | **Database** — **MySQL 8** (selaras service lain). RAG: embedding di kolom JSON + cosine similarity di aplikasi; vector store eksternal hanya bila skala membesar. | ✅ **Disepakati** (MySQL) |
| 5 | **Lokasi kode** — subfolder `curriculum-service/` di dalam `Aplikasi RPS` (sejajar `benchmark-harness/`). | ✅ **Disepakati** |

---

## 13. Langkah Berikutnya yang Disarankan

1. Konfirmasi keputusan #3–#5 di Bagian 12 (target, DB, lokasi kode).
2. Spesifikasi kontrak REST API RPS (mulai Modul 0–2) — dirancang stabil agar LMS tinggal menempel kelak.
3. Rancang `MasterDataProvider` (interface + `LocalMasterDataProvider` untuk Tahap 1) sejak awal.
4. Scaffold Curriculum Service standalone: Laravel API-only + Next.js, pindahkan `AiService` dari harness.
5. Wireframe UI risiko tinggi: Column-Mapping wizard, builder RPS dengan popover sitasi.
