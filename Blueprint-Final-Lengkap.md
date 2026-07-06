# Blueprint Final: Sistem Kurikulum & RPS OBE Terpadu (Multi-Institusi)

*Dokumen rangkuman dari seluruh proses desain — dari kasus Fakultas Farmasi UMI, digeneralisasi untuk skala universitas.*

---

## 1. Ringkasan Eksekutif

Aplikasi ini membantu institusi pendidikan tinggi menyusun, memvalidasi, dan mengevaluasi RPS berbasis OBE (Outcome Based Education) — mulai dari pemetaan CPL/kurikulum, generate draft RPS berbantuan AI, deteksi duplikasi bahan kajian antar-mata kuliah, sampai evaluasi capaian pembelajaran aktual mahasiswa sebagai dasar perbaikan berkelanjutan.

Dirancang **multi-tenant** sejak awal: setiap fakultas/prodi tinggal mengunggah data kurikulumnya sendiri (CPL, bahan kajian, mata kuliah) dan dokumen rujukan aturannya sendiri (Panduan KPT, panduan asosiasi profesi, panduan akreditasi, template RPS institusi) — tanpa developer perlu mengubah kode untuk tiap institusi baru.

---

## 2. Prinsip Desain (mengikat seluruh keputusan teknis)

1. **Configuration over code** — perbedaan antar tenant adalah data, bukan logika terpisah di kode
2. **AI menginterpretasi makna, Python menstruktur data** — tidak pernah dibalik
3. **Human-in-the-loop untuk keputusan kritikal** — approval RPS dan fakta eksplisit (jumlah minggu, bobot) selalu dikonfirmasi/diisi manusia, bukan hasil distilasi AI
4. **Traceability wajib** — setiap klaim AI bisa dilacak ke dokumen sumber & halamannya
5. **Siklus tertutup** — aplikasi menutup loop OBC → OBLT → OBAEI, bukan berhenti di dokumen RPS jadi

---

## 3. Tech Stack

> **Keputusan final (revisi):** stack diselaraskan dengan **ekosistem akademik** yang sudah berjalan (pola service lain: Laravel API-only + Next.js). Basis data **MySQL** (bukan PostgreSQL); RAG memakai **embedding tersimpan sebagai kolom JSON + cosine similarity di aplikasi** (tanpa pgvector/Pinecone). Lapisan AI **multi-provider (BYOK per tenant)**, tidak terkunci ke satu vendor.

| Layer | Teknologi | Alasan |
|---|---|---|
| Frontend | **Next.js** (React 19, Tailwind) | Selaras standar ekosistem — wizard/dashboard interaktif, pola komponen dari service lain (SortableTh, PageHeader, Shell) |
| Backend utama | **Laravel 13 (API-only, tanpa Filament)** | Selaras standar ekosistem — REST API, workflow, queue; seluruh UI di Next.js |
| Document Intelligence | **Python microservice** (tipis, stateless) | Parsing PDF/DOCX (Panduan KPT, panduan asosiasi/akreditasi, template RPS) → teks asli, **tanpa distilasi/ringkasan AI** (hindari distorsi dokumen otoritatif) |
| Lapisan AI | **Multi-provider (BYOK)** — OpenAI / Anthropic / Google / OpenRouter | Routing per-task via `config/ai.php`; kredensial per tenant di `AI_KREDENSIAL`; tidak terkunci satu vendor |
| RAG / Vector | **MySQL** — embedding sebagai kolom JSON (`DOKUMEN_CHUNK.embedding`) + cosine similarity di aplikasi | Sederhana, tanpa infra vektor terpisah; isolasi per tenant via `institusi_id` |
| Database | **MySQL 9** | Skema relasional multi-tenant (lihat ERD Master); PK bigint + ULID selektif untuk entitas lintas-service |
| Queue/Cache | Laravel Queue/Horizon (+Redis opsional) | Batch generate 100+ MK tanpa blocking |
| Storage | Object storage / disk lokal (S3-compatible) | Arsip RPS DOCX/PDF, terpisah per tenant |

Komunikasi Laravel ↔ Python: internal REST API, dipanggil hanya saat upload dokumen (jarang, bukan traffic tinggi) — didesain sebagai fungsi stateless (terima file → kembalikan JSON struktur), sehingga tim IT kampus bisa memperlakukannya seperti layanan eksternal tanpa perlu maintenance mendalam.

### 3.1 Strategi dua tahap (standalone → ekosistem)

- **Tahap 1 — Standalone:** master data akademik (mata kuliah, dosen) disimpan lokal; aplikasi berdiri sendiri.
- **Tahap 2 — Ekosistem:** master data pindah ke **Academic Core** via `MasterDataProvider` (interface seam), tanpa membongkar entitas OBE. LMS menjadi service terpisah yang mengonsumsi RPS via API.
- **Kunci migrasi mulus:** entitas OBE (CPMK/RPS) merujuk MK/dosen lewat **kunci natural** (`kode_mk`, NIDN, NIM) — **tidak pernah FK keras** ke tabel master. Semua akses master lewat `App\Support\MasterData\MasterDataProvider`.
- **Strategi PK:** semua tabel memakai bigint auto-increment; kolom **ULID unik hanya** pada entitas yang dipertukarkan lintas-service (`KURIKULUM`, `MATA_KULIAH`, `RPS_VERSION`).

---

## 4. Peta 9 Modul

| # | Modul | Fungsi Inti | Fase |
|---|---|---|---|
| 0 | **Onboarding & Ingestion** | Upload CPL/Bahan Kajian/MK (dgn Column-Mapping UI) + upload dokumen rujukan (parsing via Python, index ke vector store per-tenant) | MVP |
| 1 | **Peta Kurikulum** | Matriks CPL×Bahan Kajian, CPL×MK — fondasi sebelum generate RPS | MVP |
| 2 | **RPS Generator** | AI generate CPMK/Sub-CPMK/16 minggu/rubrik, RAG ke dokumen asli + traceability sejak awal | MVP |
| 3 | **Validator Overlap** | Deteksi otomatis bahan kajian yang diklaim >1 MK, analisis kesesuaian & rekomendasi | Fase 2 |
| 4 | **Workflow Approval** | Dosen → Kaprodi/GPM, versioning, audit trail | Fase 2 |
| 5 | **Export & Arsip** | Render ke DOCX/PDF sesuai `struktur_kolom` template tenant | Fase 3 |
| 6 | **OBAEI** (evaluasi & tindak lanjut) | Input capaian mahasiswa, agregasi ketercapaian CPL, catatan tindak lanjut yang mengalir ke siklus generate berikutnya | Fase lanjut (perlu 1 siklus semester berjalan) |
| 7 | **Traceability Layer** | Cross-cutting — anotasi sumber di tiap output AI, popover sitasi di UI | **MVP** (bagian dari cara Modul 2 bekerja sejak awal) |
| 8 | **Governance & Monitoring** | Dashboard usage/biaya per tenant, notifikasi, manajemen versi pedoman, audit log viewer | Fase scale-up (wajib begitu tenant kedua onboard) |

> **Catatan sumber data & dokumen (klarifikasi):**
> - **CPL, Bahan Kajian, Profil Lulusan, MK** = data kurikulum milik tenant, **diunggah/di-input sendiri saat Onboarding** (Modul 0, via Column-Mapping) dan tersimpan di DB Curriculum Service (`profil_lulusan`, `cpl`, `bahan_kajian`, `mata_kuliah`, dikelompokkan per `KURIKULUM`). Di Tahap 2 ekosistem, hanya **MK/dosen** yang bersumber dari Academic Core; **artefak OBE (CPL/BK/CPMK/RPS) tetap dimiliki Curriculum Service**.
> - **Dokumen rujukan** (KPT/SN-Dikti, aturan asosiasi, aturan lembaga akreditasi, template RPS) **selalu berasal dari upload → diparse Python → `DOKUMEN_CHUNK`** — tidak ada yang di-*hardcode*. Pola **hibrida**: dokumen nasional/umum di-seed **sekali sebagai global** (`institusi_id = null`, mis. KPT/SN-Dikti) sehingga dipakai bersama tanpa tiap tenant mengunggah ulang; dokumen lokal/prodi diunggah **per tenant**. Tenant boleh menambah/override versi sendiri.

---

## 5. Alur Data Utama

Tiga diagram pendukung (file terpisah):
- `1-arsitektur-sistem.mermaid` — peta komponen awal
- `4-arsitektur-multi-tenant.mermaid` — versi dengan modul Onboarding
- `2-alur-generate-rps.mermaid` — sequence generate 1 RPS + validasi
- `8-siklus-obaei.mermaid` — siklus tertutup OBC→OBLT→OBAEI

**Alur singkat generate 1 RPS:**
```
Dosen isi data MK → Backend ambil CPL/Bahan Kajian terkait dari DB
   → RAG retrieve teks relevan dari dokumen rujukan asli (bukan hasil distilasi)
   → Claude generate draft RPS + simpan SOURCE_CITATION per klaim
   → Validator Overlap cek otomatis jika bahan kajian diklaim >1 MK
   → Dosen review (bisa klik sitasi utk verifikasi) → submit
   → Kaprodi/GPM approve → versi terkunci → export DOCX
```

---

## 6. Skema Database

Lihat `9-ERD-master.mermaid` — gabungan seluruh entitas dari semua modul, termasuk:
- Inti kurikulum: `KURIKULUM` (payung versi), `INSTITUSI`, `PROFIL_LULUSAN`, `CPL`, `TARGET_CPL` (kalibrasi OBAEI), `PL_CPL`, `BAHAN_KAJIAN`, `KETERAMPILAN` (+domain C/A/P), `MATA_KULIAH` (+`jenis_mk` murni/praktikum, +`kurikulum_id`), `MK_PENGAMPU` (kepemilikan MK), `CPMK`, `CPMK_CPL`, `SUB_CPMK` (+`taksonomi_id`), `INDIKATOR`, `TAKSONOMI` (master level kognitif/afektif/psikomotorik + kata kerja operasional)
- RPS & versioning: `RPS_VERSION`, `RPS_MINGGU`, `KOMPONEN_PENILAIAN` (rencana asesmen → Sub-CPMK), `RUBRIK` + `RUBRIK_KRITERIA`, `REFERENSI`
- Generator: `GENERATE_SESSION` (staging draf bertahap → commit), `PROMPT_TEMPLATE` (jenis_output × jenis_mk, skema output JSON)
- Multi-tenant & onboarding: `DOKUMEN_RUJUKAN`, `DOKUMEN_CHUNK` (RAG), `VERSI_PEDOMAN`, `TEMPLATE_RPS`, `KONFIGURASI_ATURAN`, `COLUMN_MAPPING`
- Kerangka acuan (checklist lintas otoritas): `BADAN_RUJUKAN`, `KERANGKA_ACUAN`, `BUTIR_ACUAN`, `PEMENUHAN_ACUAN`
- Validasi & traceability: `VALIDASI_OVERLAP`, `SOURCE_CITATION`
- Lapisan AI: `AI_KREDENSIAL`, `AI_INTERAKSI` (log panggilan), `AI_VALIDASI` (validator anti-halusinasi per klaim)
- OBAEI: `CAPAIAN_MAHASISWA`, `EVALUASI_CPL`, `TINDAK_LANJUT`
- Governance: `NOTIFIKASI`, `AUDIT_LOG`, `USER`

**Catatan penting:** `KONFIGURASI_ATURAN` (jumlah minggu, total bobot, dsb) **diisi manual oleh admin/Kaprodi** yang membaca dokumen sendiri — bukan hasil distilasi otomatis AI, sesuai keputusan final untuk menjaga akurasi aturan yang sifatnya otoritatif.

### 6.1 Mata Kuliah Murni vs Praktikum, Konversi SKS, & Taksonomi

- **`jenis_mk` (murni / praktikum) = pembeda kaku.** Banyak institusi memisahkan tegas MK teori dan praktikum; jenis ini menentukan: domain taksonomi dominan, jenis komponen penilaian, template prompt AI, dan cara menghitung estimasi waktu.
- **Konversi SKS → jam otomatis** (disimpan di `KONFIGURASI_ATURAN`, tenant boleh override): teori 1 SKS/minggu = 50′ tatap muka + 60′ tugas terstruktur + 60′ mandiri (170′); praktikum 1 SKS = 170′ praktik. Ditampilkan per minggu & per semester (×16).
- **`TAKSONOMI` (master, dapat di-seed global).** Tiga domain × kerangka: **kognitif** (Bloom–Anderson C1–C6), **afektif** (Krathwohl A1–A5), **psikomotorik** (Dave/Simpson P1–P7), lengkap dengan daftar **kata kerja operasional terukur**. Dipakai sebagai: dropdown builder, guardrail deterministik (menolak kata kerja tak terukur), dan konteks prompt AI.
- Keterkaitan: `SUB_CPMK.taksonomi_id`, `KETERAMPILAN.domain + taksonomi_id`, `MK_KETERAMPILAN.taksonomi_id`.
- **`MK_PENGAMPU`** memisahkan kepemilikan/koordinasi MK (koordinator vs anggota) dari data MK; OBE tetap merujuk MK lewat **kunci natural `kode_mk`**, bukan FK keras.

---

## 7. Strategi AI

### 7.1 Prinsip dasar
| Aspek | Pendekatan Final |
|---|---|
| Sumber aturan (Panduan KPT, dst) | Parsing langsung ke teks asli via Python → `DOKUMEN_CHUNK` (embedding JSON) — **tanpa** tahap ringkasan/distilasi AI |
| Interpretasi saat generate | Dilakukan AI real-time dengan RAG, merujuk teks asli — bukan "hasil olahan" yang disimpan permanen |
| Fakta eksplisit & kaku (16 minggu, bobot 100%, konversi SKS) | Diisi/di-set manusia via `KONFIGURASI_ATURAN`, divalidasi silang oleh rule-engine deterministik |
| Traceability | Setiap klaim AI menyimpan `SOURCE_CITATION` (dokumen + halaman + cuplikan) |
| Multi-provider (BYOK) | Kredensial per tenant (`AI_KREDENSIAL`); routing per-task via `config/ai.php`; tidak terkunci satu vendor |
| Efisiensi biaya | Prompt caching konteks per-tenant, Batch API untuk generate massal, mayoritas task memakai model "light" |

### 7.2 Enam mode AI
1. **Generatif** — draf CPMK/Sub-CPMK/rencana mingguan/rubrik (model frontier, temperatur sedang).
2. **Asistif inline** — bantu sunting/parafrase/perbaikan satu field saat dosen mengetik.
3. **Konversasional** — tanya-jawab kurikulum berbasis konteks tenant (RAG).
4. **Penyelaras acuan** — cocokkan PL/CPL/bahan kajian ke `BUTIR_ACUAN` (checklist), tandai belum terpenuhi + rekomendasi.
5. **Validator anti-halusinasi** — ekstrak klaim dari output generatif → cek terhadap chunk RAG + data → skor grounding → kembalikan konteks yang benar → regenerasi otomatis maks 1× atau tandai review. **Wajib memakai provider/model berbeda dari generator** (cross-check). Disimpan di `AI_VALIDASI`.
6. **Evaluatif (judge)** — nilai mutu draf akhir sebelum dikomit (model frontier, temperatur 0).

### 7.3 Peran AI per field (routing model)
Setiap field diklasifikasi berdasarkan "siapa yang paling tepat mengisinya", agar model dipilih sesuai tanggung jawab:
- **M (Manusia, otoritatif)** — deskripsi CPL/PL/Bahan Kajian (AI dilarang mengarang), nilai `KONFIGURASI_ATURAN`.
- **D (Deterministik)** — `estimasi_waktu` (konversi SKS), kode CPMK, `SOURCE_CITATION`, agregasi capaian.
- **G1 (Generatif frontier)** — deskripsi CPMK/Sub-CPMK/mingguan/rubrik/indikator → lalu **disunting manusia**.
- **E2 (Ekstraksi/klasifikasi, model ringan + JSON schema)** — aspek CPL, domain keterampilan, column-mapping, ekstraksi butir acuan & klaim.
- **V2 (Validator grounding, provider ≠ generator)** — verifikasi `PEMENUHAN_ACUAN`, klaim RPS.
- **J1 (Judge frontier)** — penilaian mutu akhir.
- **EMB (Embedding)** — indexing `DOKUMEN_CHUNK` + pencocokan kandidat.

Routing diatur di `config/ai.php` (`task → tier/temperatur/schema/cross_provider`). Tier dipetakan ke model konkret per tenant lewat `AI_KREDENSIAL`: **frontier** (mis. Claude Sonnet / GPT-4o), **light** (mis. Haiku / 4o-mini / Gemini Flash), **embedding** (mis. text-embedding-3-small / bge-m3). **Structured output wajib** untuk setiap task ber-skema. Hanya sedikit task memakai frontier (generate, judge, narasi); mayoritas volume memakai model light (~80% panggilan, <20% biaya). Setiap panggilan dicatat di `AI_INTERAKSI` (token + biaya).

### 7.4 Pipeline generator bertahap (human-in-the-loop)
Generate RPS berjalan **bertahap dengan checkpoint manusia**, bukan sekali jadi:
```
CPMK → [review] → Sub-CPMK + Indikator → [review] → 16 minggu → [review]
     → Komponen Penilaian + Rubrik → [review] → Commit
```
> **ATURAN KERAS — tidak boleh generate seluruh RPS sekaligus.** Setiap tahap = **satu panggilan AI terpisah** dengan `skema_output` JSON sempit untuk tahap itu saja (bukan satu mega-prompt yang menghasilkan seluruh dokumen). Tahap berikutnya **hanya boleh** berjalan setelah tahap sebelumnya lolos validator dan (bila diminta) dikonfirmasi manusia; keluaran tahap sebelumnya menjadi **konteks input** tahap berikutnya. Alasan: (a) kontrol mutu & grounding per bagian, (b) memungkinkan regenerasi parsial tanpa membongkar bagian lain, (c) menahan halusinasi & menjaga koherensi taksonomi/bobot, (d) hemat biaya (prompt caching konteks tenant antar-tahap).
- **`GENERATE_SESSION`** menyimpan draf staging per tahap (JSON) + status tiap bagian (diterima/disunting/ditolak/di-pin). Kolom `tahap` melacak tahap aktif; `status_bagian` menandai bagian yang sudah dikunci. Regenerasi parsial dimungkinkan; bagian yang sudah disunting bisa dikunci agar tidak tertimpa saat tahap lain di-generate ulang.
- **`PROMPT_TEMPLATE`** = pustaka prompt versioned per `jenis_output × jenis_mk`, per tenant (null = default global), dengan `skema_output` JSON. **Satu template = satu tahap/`jenis_output`** (mis. `cpmk`, `sub_cpmk`, `mingguan`, `penilaian`), sehingga tidak ada template "generate semua".
- Setiap tahap melewati validator anti-halusinasi (mode 5) sebelum lanjut; tahap yang gagal validasi **berhenti** dan tidak memicu tahap berikutnya.
- **Rule-engine kelengkapan (tanpa AI)** menghitung "health score": Σbobot CPMK/komponen/minggu = 100%, tiap CPMK ≥1 Sub-CPMK & ≥1 CPL, tiap Sub-CPMK ≥1 indikator + minggu + komponen, kata kerja ∈ `TAKSONOMI` sesuai domain `jenis_mk`, Σ`estimasi_waktu` = hasil konversi SKS.
- Jalur opsional (Fase 2): impor RPS lama (Word/Excel) → parse → gap analysis → masuk pipeline; reuse tahunan (copy tahun lalu + suntik `TINDAK_LANJUT`).

### 7.5 Kebijakan pengetahuan umum AI (grounding ketat)

AI boleh memanfaatkan pengetahuan global bawaannya, **tetapi tidak boleh berhalusinasi atau mengambil sembarangan**. Aturannya:
- **Dokumen (upload/seed global) = sumber otoritatif tunggal** untuk klaim normatif (aturan KPT/SN-Dikti, syarat akreditasi, aturan asosiasi). Pengetahuan parametrik AI hanya boleh sebagai **bantuan menyusun bahasa/redaksi**, **bukan** sumber fakta aturan.
- **Mode ketat (strict) untuk kategori otoritatif:** setiap klaim yang menyangkut aturan/akreditasi **wajib ter-*grounding*** ke `DOKUMEN_CHUNK`/`BUTIR_ACUAN`. Klaim tanpa bukti chunk → ditandai `tak_didukung` oleh **Validator anti-halusinasi (mode 5)** → **tidak dikomit**; disajikan sebagai saran yang perlu konfirmasi manusia, bukan pernyataan final.
- **Dapat dikonfigurasi:** daftar kategori ketat diatur di `config/ai.php` (mis. `strict_categories: [aturan, akreditasi]`), memanfaatkan `BADAN_RUJUKAN.jenis` (pemerintah/asosiasi/akreditasi) untuk menentukan mana yang otoritatif. Di mode ketat, *fallback* ke pengetahuan umum AI **dinonaktifkan** — harus ada sitasi, atau tidak menghasilkan klaim.
- **Konten non-normatif** (draf redaksi CPMK, contoh kalimat) boleh memakai pengetahuan umum AI lebih longgar, tetap melalui review manusia.

### 7.6 Pemetaan model konkret (OpenAI + Claude, "tidak overkill")

Preferensi: **OpenAI + Claude**. Model dipilih sesuai berat tugas — frontier hanya untuk yang benar-benar butuh; Opus disimpan sebagai eskalasi, bukan default. Pemetaan disimpan di `config/ai.php` dan dapat di-override per tenant via `AI_KREDENSIAL.model_default`.

| Tugas | Volume | Model | Alasan tidak overkill |
|---|---|---|---|
| Generate RPS (CPMK/Sub-CPMK/16 minggu/rubrik) | sedang | **Claude Sonnet 5** | Kualitas redaksi & penalaran pedagogis; Opus 2,5× lebih mahal & berlebihan |
| Judge / QA mutu akhir | rendah | **GPT-5.4** | Kuat, **beda provider** dari generator (cross-check), lebih murah dari Opus |
| Validator anti-halusinasi (grounding) | tinggi | **GPT-5.4-mini** | Wajib beda provider dari Sonnet; cukup untuk cek klaim vs chunk |
| Asistif inline / parafrase | tinggi | **GPT-5.4-mini** | Ringan & cepat, tak perlu frontier |
| Ekstraksi/klasifikasi (aspek CPL, mapping, butir acuan) | tinggi | **GPT-5.4-mini** + JSON schema | Tugas terstruktur; nano terlalu lemah untuk nuansa |
| Konversasional (tanya-jawab RAG) | sedang | **GPT-5.4-mini** → eskalasi **Sonnet 5** | Hemat, naik model hanya saat kompleks |
| Embedding (index RAG) | sekali (tinggi) | **text-embedding-3-small** | Termurah & cukup; Claude tak punya model embedding |
| Eskalasi kasus sulit (opsional) | sangat rendah | **Claude Opus 4.8** | Hanya saat sengketa/buntu, bukan default |

Pembagian provider: **Claude Sonnet 5** = otak generatif; **OpenAI (mini/5.4)** = pekerja ringan + validator + judge → otomatis memenuhi syarat **cross-provider** untuk validator anti-halusinasi.

---

## 8. Estimasi Biaya (harga model per Jul 2026, tersimpan di `config/ai.php`)

**Harga referensi** (USD per 1M token, input / output):

| Model | Peran | Input | Output |
|---|---|---|---|
| Claude Sonnet 5 | generator utama | $2 (→$3 per Sep 2026) | $10 (→$15) |
| GPT-5.4 | judge / QA | $2,50 | $15 |
| GPT-5.4-mini | validator, asistif, ekstraksi | $0,75 | $4,50 |
| Claude Opus 4.8 | eskalasi (opsional) | $5 | $25 |
| text-embedding-3-small | embedding RAG | ≈ $0,02 | — |

**Estimasi biaya** (mix kualitas: generator Sonnet 5; ~30k in / 10k out per RPS):

| Komponen | Tanpa optimasi | +Caching +Batch |
|---|---|---|
| 1 RPS penuh (generate + validate + judge) | ≈ $0,23 | ≈ $0,10–0,13 |
| 100 RPS | ≈ $23 | ≈ $10–13 |
| Onboarding per tenant (index dokumen + ekstraksi) | ≈ $0,05–0,20 (sekali) | — |

> **Lever hemat & prinsip tidak overkill:** (a) generator turun ke GPT-5.4-mini (mix budget) → ~½ biaya (≈ $0,13/RPS), kualitas redaksi sedikit turun; (b) prompt caching konteks tenant (cache read 0,1× input); (c) Batch API −50% untuk generate massal non-real-time. **Opus hanya untuk eskalasi**, tidak dipakai sebagai default. Harga model berubah sewaktu-waktu — sumber kebenaran = tabel harga di `config/ai.php`, dan biaya aktual tetap dilog di `AI_INTERAKSI`.

### 8.1 Monitoring Token & Biaya (per tenant / per pengguna)

Karena tiap panggilan AI dicatat di `AI_INTERAKSI` (`tokens_in`, `tokens_out`, `biaya`, `provider`, `model`), dashboard biaya (Modul 8) menampilkan:
- **Token terpakai** — akumulasi `tokens_in + tokens_out` (per hari/bulan, per modul, per pengguna). **Selalu tersedia** karena setiap respons API mengembalikan objek `usage`.
- **Harga token & biaya** — dihitung dari **tabel harga per model** (di `config/ai.php`, sebab provider tidak mengirim harga dalam respons) × jumlah token. Ditampilkan real-time per generate & agregat.
- **Penggunaan** — rincian per task/mode (generate/validate/embedding) dan per model (frontier vs light) untuk melihat komposisi biaya.
- **Sisa kuota** — dari dua sumber:
  1. **Anggaran lokal (andal, disarankan):** `AI_KREDENSIAL.anggaran` = batas biaya per periode; *sisa = anggaran − Σbiaya terpakai*. Bisa memicu peringatan/blokir saat mendekati batas.
  2. **Saldo provider (opsional, bergantung provider):** sebagian provider mengekspos sisa kredit via API (mis. OpenRouter). **OpenAI/Anthropic umumnya TIDAK** mengekspos saldo BYOK secara andal — jadi "sisa token" untuk key BYOK paling akurat dihitung dari anggaran lokal, bukan dari provider. Bila tersedia, di-cache di `AI_KREDENSIAL.saldo_provider`.

> Ringkas: **jumlah token, harga, dan penggunaan** dapat ditampilkan penuh (dari respons API + tabel harga). **"Sisa token/saldo"** tidak selalu bisa diambil lewat API key (tergantung provider); solusi universal = kuota/anggaran yang kita kelola sendiri.

---

## 9. Keamanan & Multi-Tenancy

- Setiap tabel inti punya `institusi_id` — isolasi data ketat antar fakultas
- RBAC: dosen (data MK sendiri) / Kaprodi-GPM (lintas MK dalam prodi) / admin pusat (lintas institusi)
- RAG diisolasi per tenant via `institusi_id` pada `DOKUMEN_CHUNK` — dokumen rujukan 1 fakultas tidak pernah "bocor" ke RAG fakultas lain
- Audit log lengkap untuk kebutuhan akreditasi (siapa ubah apa, kapan)

---

## 10. Roadmap Implementasi

| Fase | Cakupan | Validasi Keberhasilan |
|---|---|---|
| **Fase 1 — MVP** | Modul 0, 1, 2 (+Traceability), pilot 3-5 MK Farmasi UMI | Draft RPS akurat, sitasi sumber berfungsi |
| **Fase 2** | Modul 3 (Validator Overlap), Modul 4 (Workflow) | Kasus overlap seperti "Pengecilan Ukuran Partikel" terdeteksi otomatis |
| **Fase 3** | Modul 5 (Export), scale ke 100 MK Farmasi UMI | Batch generate 100 MK sukses, biaya sesuai estimasi |
| **Fase 4 — Uji Generalisasi** | Onboarding tenant kedua (fakultas lain) | **Tidak ada perubahan kode** dibutuhkan — kalau ini gagal, arsitektur multi-tenant perlu direvisi |
| **Fase 5** | Modul 6 (OBAEI) | Setelah minimal 1 siklus semester berjalan dengan data nilai nyata |
| **Fase 6 — Scale-up** | Modul 8 (Governance), rollout ke seluruh universitas | Dashboard admin pusat aktif, multi-fakultas berjalan bersamaan |

---

## 10.1 Status Pembangunan Saat Ini (2026-07-04)

Fondasi backend **Curriculum Service** sudah berdiri (UI belum dibangun):
- Laravel 13 (API-only, tanpa Filament), PHP 8.4, DB MySQL `curriculum_service`. Endpoint `GET /api/v1/health` teruji. Auth ditunda ke tahap akhir (pola ekosistem).
- **Seam master data** `MasterDataProvider` + `LocalMasterDataProvider` aktif (binding di `AppServiceProvider`).
- **Skema database lengkap** — 9 berkas migrasi: `institusi/kurikulum/mata_kuliah/mk_pengampu`; referensi + `dokumen_chunk`; kurikulum OBE + `taksonomi/target_cpl`; CPMK/RPS + `komponen_penilaian/rubrik/rubrik_kriteria`; compliance; OBAEI; governance; **generator** (`generate_session/prompt_template`); **AI** (`ai_kredensial/ai_interaksi/ai_validasi`). `migrate:fresh` sukses.
- **±46 model Eloquent** selaras ERD final: ULID auto-generate untuk `Kurikulum/MataKuliah/RpsVersion`, cast JSON/decimal/date, relasi + accessor `sks = sks_teori + sks_praktik`. Teruji via tinker (ULID, akssesor SKS, relasi, cascade delete bersih).
- **Lapisan AI (`AiService`)** — diport dari `benchmark-harness`: driver multi-provider (`OpenAiDriver`/`AnthropicDriver`/`MockDriver`), `DriverManager`, `LlmResult`, `CostCalculator`, dan orchestrator `AiService` yang task-aware (`config/ai.php`: routing per-tugas 7.6, katalog model + harga, `strict_categories`), BYOK per tenant dari `AI_KREDENSIAL` (fallback env -> mock dev), guard **lintas-provider** untuk validator, **budget guard** (kuota `anggaran`), dan logging tiap panggilan ke `AI_INTERAKSI` (token + biaya USD). Teruji end-to-end via mock: routing, cross-provider, budget block, cascade-delete log.
- **Pipeline generator bertahap (`RpsGeneratorService`)** — menegakkan aturan keras 7.4: `config/generator.php` (urutan tahap `cpmk -> sub_cpmk -> mingguan -> penilaian`, prasyarat `context_from`, skema JSON + prompt sistem default per tahap). Metode `start/generateStage/acceptStage/rejectStage/pinStage/readyToCommit`: **satu panggilan AI per tahap**, tahap berikutnya diblokir sampai prasyaratnya disetujui/dikunci, prompt tiap tahap dirakit dari `PROMPT_TEMPLATE` (fallback default) + data MK + CPL + keluaran tahap sebelumnya, hasil disimpan ke `GENERATE_SESSION.draf`/`status_bagian`. Metode `commit()` menulis draf ke entitas RPS resmi dalam satu transaksi: `CPMK`(+pivot `CPMK_CPL`), `SUB_CPMK`(+`INDIKATOR`), `RPS_VERSION` (versi berikutnya + ULID), `RPS_MINGGU`, dan `KOMPONEN_PENILAIAN`, lalu menandai sesi `committed`. Teruji offline via mock: gating urutan, penguncian `pinned`, commit materialisasi entitas, guard commit-belum-siap, cascade-delete bersih.
- **Embedding RAG (`EmbeddingService`)** — vektor teks via OpenAI `text-embedding-3-small` (BYOK tenant -> env -> mock deterministik dev), disimpan di `DOKUMEN_CHUNK.embedding` (JSON). Metode `embed/embedChunk/search`: pencarian kemiripan **kosinus di dalam aplikasi** (tanpa pgvector) dengan filter tenant + `topK` + `min_score`, setiap panggilan dicatat ke `AI_INTERAKSI` (mode `embedding`). `AI_INTERAKSI.institusi_id` dibuat nullable untuk panggilan sistem/non-tenant. Teruji offline: penyimpanan vektor, ranking kosinus, determinisme, cascade-delete.
- **Validator anti-halusinasi (`GroundingValidator`, mode 5 + grounding ketat 7.5)** — per klaim: retrieval bukti via `EmbeddingService::search` (RAG); tanpa bukti memadai -> guardrail deterministik `tak_didukung` (tanpa memanggil LLM); ada bukti -> penilaian LLM task `validator` (**wajib lintas-provider** dari generator) menghasilkan `grounded/tak_didukung/kontradiktif` + skor + konteks pengganti. Klaim kategori ketat (`config('ai.strict_categories')`) yang tak grounded -> tindakan `tolak` (tak boleh dikomit); non-ketat -> `revisi_ulang`. Setiap klaim dicatat ke `AI_VALIDASI`. Parameter di `config('ai.grounding')`. Teruji offline: jalur grounded/terima, strict-tolak, non-strict-revisi, guard anchor interaksi, cascade-delete.
- **Auto-regenerasi grounded (`RpsGeneratorService::generateStage`)** — tiap tahap kini otomatis divalidasi ke `DOKUMEN_CHUNK` setelah digenerate; klaim tak-grounded memicu **regenerasi otomatis** maksimal `config('ai.grounding.auto_revisi_maks')` kali dengan menyuntikkan blok "KOREKSI WAJIB" (konteks pengganti dari bukti sahih) ke prompt. Bila jatah habis atau tak ada konteks yang bisa diinjeksikan, tahap ditandai `perlu_review`. Ringkasan tersimpan di kolom baru `GENERATE_SESSION.catatan_validasi`. Divalidasi hanya bila tenant punya dokumen rujukan berembedding (jika tidak, dilewati). Dapat dimatikan via `config('generator.grounding.enabled')`. Teruji offline: jalur dilewati (tanpa dokumen), grounded/bersih, regen-dengan-konteks (2 panggilan), perlu-review tanpa konteks, dan mode nonaktif.
- **Modul 1 — Peta Kurikulum (REST API)** — lapisan HTTP pertama (prefix `/api/v1`, pola ekosistem: `AppliesSorting` whitelist-kolom, `JsonResource`, pagination `meta`). CRUD `KURIKULUM`/`PROFIL_LULUSAN`/`CPL`/`MATA_KULIAH` (filter + `sort/dir` aman + `q`), dengan `institusi_id` CPL/PL diturunkan dari kurikulum (bukan input klien). `PetaKurikulumController` menyediakan **matriks MK×CPL** (`GET/POST/DELETE kurikulum/{k}/matriks[/link]`, upsert `MK_CPL` dengan guard keanggotaan kurikulum -> 422) dan **traceability** (`GET kurikulum/{k}/traceability`: tiap CPL + MK pengembannya + deteksi CPL yatim). Teruji E2E live: CRUD + ULID auto + accessor SKS + matriks + guard 422 + traceability (CPL yatim terdeteksi) + fallback sort injection (200).
- **Modul 2 — RPS Generator (REST API)** — mengekspos pipeline bertahap `RpsGeneratorService` via HTTP. `GENERATE_SESSION` apiResource (index/store/show, filter + sort) + aksi `POST generate-sessions/{s}/(generate|accept|reject|pin|commit)`; setiap `generate/accept/reject/pin` memvalidasi `stage ∈ config('generator.pipeline')` dan menegakkan gating prasyarat (tahap N+1 diblok 422 sebelum tahap N disetujui). `commit` menulis draf → entitas RPS resmi (201 `{session,rps}`) dengan **guard idempoten** (sesi ber-status `committed`/`rps_version_id` terisi → 422, mencegah RPS duplikat). Baca hasil: `GET rps-versions` (filter+sort), `GET rps-versions/{v}` (rps+minggu+komponen), `GET rps-versions/{v}/traceability` (rantai Sub-CPMK→CPMK→CPL + `cpl_diampu`). `catatan_validasi` grounding ikut di `GenerateSessionResource`. Teruji E2E live (mock LLM): start → generate/accept 4 tahap → gating 422 → commit 201 → traceability → commit-ulang 422 → stage liar 422.
- **Modul 0 — Onboarding & Column-Mapping (REST API)** — impor data kurikulum tanpa dependensi spreadsheet: terima **CSV teks** (parse `str_getcsv` di server) atau **rows JSON** (frontend mem-parse XLSX → array-of-objects / array-of-arrays). `POST onboarding/preview` mengembalikan header + contoh baris + **saran pemetaan kolom deterministik** (kata-kunci → field target; ekstraksi PDF/DOCX ditunda ke microservice Python). `GET/POST onboarding/mapping` menyimpan `COLUMN_MAPPING` per (institusi, jenis) via `updateOrCreate`. `POST onboarding/import` menerapkan pemetaan (input → tersimpan → saran) dan **upsert** baris ke `CPL`/`MATA_KULIAH`/`BAHAN_KAJIAN` per kurikulum dengan kunci natural (mencegah duplikat), mengembalikan ringkasan `{dibuat, diperbarui, dilewati, galat[]}` (baris field-wajib-kosong dilewati, sel non-numerik pada kolom int dicatat sebagai galat). Guard keanggotaan kurikulum → 422. Teruji E2E live: preview→saran benar, import CSV (2 dibuat) + import ulang (1 diperbarui, bukan duplikat) + rows JSON MK (SKS/jenis benar, ULID auto) + baris cacat (dilewati/galat) + guard 422.

**Berikutnya (Modul MVP, berurutan):** Frontend Next.js (Shell/PageHeader/SortableTh/Pagination footer) untuk Modul 0/1/2; microservice Python doc-intel (ekstraksi dokumen -> chunk -> `EmbeddingService`); commit `RUBRIK`/`RUBRIK_KRITERIA` saat template penilaian menyertakan rubrik. Auth Keycloak ditunda paling akhir (pola ekosistem).

---

## 11. Daftar File Pendukung

| File | Isi |
|---|---|
| `1-arsitektur-sistem.mermaid` | Arsitektur komponen awal |
| `2-alur-generate-rps.mermaid` | Sequence diagram generate + validasi |
| `3-erd-database.mermaid` | ERD awal (sebelum multi-tenant) |
| `4-arsitektur-multi-tenant.mermaid` | Arsitektur dengan modul Onboarding |
| `5-addendum-multi-tenant.md` | Detail entitas & alur onboarding generic |
| `6-column-mapping-template-biaya.md` | Detail UI Column-Mapping, skema Template RPS, estimasi biaya |
| `7-konsep-ideal-lengkap.md` | Penjelasan 3 modul baru (OBAEI, Traceability, Governance) |
| `8-siklus-obaei.mermaid` | Diagram siklus tertutup OBC→OBLT→OBAEI |
| `9-ERD-master.mermaid` | **ERD gabungan final, seluruh entitas** |
| `Blueprint-Arsitektur-Aplikasi-RPS-OBE.md` | Blueprint awal (versi single-tenant, historis) |
| **`Blueprint-Final-Lengkap.md`** | **Dokumen ini — rangkuman keseluruhan** |

---

## 12. Langkah Berikutnya yang Disarankan

Blueprint ini sudah cukup matang untuk dibawa ke tim dev/pimpinan sebagai dasar proposal. Yang biasanya dibutuhkan setelah tahap ini:
1. Spesifikasi API endpoint (REST contract) per modul
2. Wireframe UI risiko tinggi (Column-Mapping wizard, dashboard Validator Overlap)
3. Proposal timeline & anggaran detail per fase untuk keperluan persetujuan internal
