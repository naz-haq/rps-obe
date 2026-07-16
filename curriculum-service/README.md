# curriculum-service — Backend API (Laravel)

Backend REST API untuk aplikasi **RPS OBE**. Menyimpan seluruh data kurikulum & RPS (source of truth), menjalankan pipeline **generator RPS bertahap**, lapisan **AI multi‑provider**, dan **RAG grounding** (anti‑halusinasi).

> Dokumentasi produk & panduan pengguna: lihat [README root](../README.md).
> Frontend: lihat [`../curriculum-web/README.md`](../curriculum-web/README.md).

---

## Daftar Isi

1. [Stack & Prasyarat](#1-stack--prasyarat)
2. [Setup Lokal](#2-setup-lokal)
3. [Variabel Lingkungan](#3-variabel-lingkungan)
4. [Struktur Kode](#4-struktur-kode)
5. [Model Data & Konvensi](#5-model-data--konvensi)
6. [Lapisan AI](#6-lapisan-ai)
7. [Pipeline Generator RPS](#7-pipeline-generator-rps)
8. [RAG & Grounding (Anti‑Halusinasi)](#8-rag--grounding-anti-halusinasi)
9. [Daftar Endpoint](#9-daftar-endpoint)
10. [Autentikasi & RBAC](#10-autentikasi--rbac)
11. [Antrean & Job](#11-antrean--job)
12. [Pengujian](#12-pengujian)
13. [Catatan Penting (Gotchas)](#13-catatan-penting-gotchas)

---

## 1. Stack & Prasyarat

| Komponen | Versi |
|---|---|
| PHP | 8.4 (constraint `^8.3`) |
| Laravel | 13 (`^13.8`) |
| Database | MySQL 8.4 |
| Auth | Laravel Sanctum (bearer token) |
| RBAC | spatie/laravel-permission |
| Cetak DOCX | phpoffice/phpword |
| Ekstraksi PDF | smalot/pdfparser |
| Antrean | Queue driver `database` |

Prefix API: **`/api/v1`** (di `bootstrap/app.php`).

---

## 2. Setup Lokal

```bash
cd curriculum-service
composer install
cp .env.example .env
php artisan key:generate

# Konfigurasi DB MySQL di .env, lalu:
php artisan migrate --seed

# Jalankan (port 8100 dipakai frontend saat dev)
php artisan serve --port=8100
```

Cek kesehatan: `GET http://127.0.0.1:8100/api/v1/health` → `{"service":"curriculum-service","status":"ok"}`.

> **Jangan** menjalankan `php artisan serve` dua kali di port yang sama (`Address already in use`). Artisan serve membaca route baru tiap request, jadi tak perlu restart setelah mengubah route.

Validasi cepat sintaks: `php84 -l app/Path/File.php`.

---

## 3. Variabel Lingkungan

Kunci penting (selain DB/APP standar Laravel):

| Variabel | Fungsi | Default |
|---|---|---|
| `AI_PROFILE` | Profil AI default bila DB tak menentukan | `simulasi` |
| `AI_FALLBACK_MOCK` | Jika `true`, panggilan gagal jatuh ke MockDriver | `false` (prod) |
| `GEMINI_API_KEY` / `OPENAI_API_KEY` / `ANTHROPIC_API_KEY` / `DEEPSEEK_API_KEY` / `NVIDIA_API_KEY` | Kredensial provider (env atau BYOK per‑tenant) | — |
| `NVIDIA_BASE_URL` | Endpoint NVIDIA NIM (kompatibel OpenAI) | `https://integrate.api.nvidia.com/v1` |
| `AI_EMBED_PROVIDER` / `AI_EMBED_MODEL` / `AI_EMBED_DIMENSIONS` | Provider & model embedding RAG | `nvidia` / `nvidia/nv-embedqa-e5-v5` / `1024` |
| `AI_GROUNDING_MIN_SCORE` | Ambang cosine minimal bukti grounding | `0.4` |

> Di produksi, hanya variabel yang **terdaftar eksplisit** di blok `environment:` [`docker-compose.prod.yml`](../docker-compose.prod.yml) yang sampai ke kontainer. Kunci AI baru **wajib** ditambahkan di sana.

---

## 4. Struktur Kode

```
app/
├── Http/
│   ├── Controllers/Api/     # Controller REST per modul
│   ├── Controllers/Concerns/AppliesSorting.php   # whitelist sort (anti SQL-injection)
│   └── Resources/           # API Resources (bentuk JSON respons)
├── Models/                  # ~33 model Eloquent
├── Services/
│   ├── Ai/                  # AiService, Drivers, EmbeddingService, GroundingValidator, PromptRepository
│   ├── Generator/           # RpsGeneratorService (pipeline bertahap)
│   └── Onboarding/          # OnboardingImportService (impor CSV/rows)
├── Jobs/                    # IngestDokumenJob (indexing RAG asinkron)
└── Support/MasterData/      # Seam MasterDataProvider (persiapan ekosistem)
config/
├── ai.php                   # Provider, model, profil, tasks, grounding
├── generator.php            # Definisi pipeline & tahap
└── prompts.php              # Prompt AI terpusat (slot per tahap)
routes/api.php               # Semua endpoint (prefix /api/v1)
database/migrations/         # Skema (PK bigIncrements, institusi_id di tabel inti)
```

---

## 5. Model Data & Konvensi

- **PK** `bigIncrements`. Kolom `ulid` unik **hanya** pada entitas yang ditukar lintas‑service: `kurikulum`, `mata_kuliah`, `rps_version`.
- **Multi‑tenant:** semua tabel inti punya `institusi_id` FK → `institusi` (cascade).
- **Seam master data:** entitas OBE merujuk MK/dosen lewat **kunci natural** (`kode_mk`, `dosen_nidn`, `nim`) — **tanpa FK keras** ke tabel master. Akses master lewat `App\Support\MasterData\MasterDataProvider`.
- **Matriks OBE** sebagai tabel pivot: `pl_cpl`, `mk_cpl`, `cpmk_cpl`, `mk_keterampilan`, dll. (menyimpan `institusi_id` + bobot).
- **FK penting:** `generate_session.rps_version_id` → `rps_version` (**nullOnDelete**); `rps_minggu` & `komponen_penilaian` → `rps_version` (**cascadeOnDelete**).

ERD lengkap: [`../9-ERD-master.mermaid`](../9-ERD-master.mermaid).

---

## 6. Lapisan AI

Entry point tunggal:

```php
App\Services\Ai\AiService::run(
    string $task,     // 'generate' | 'judge' | 'validator' | 'asistif' | 'ekstraksi' | ...
    string $system,   // system prompt
    string $prompt,   // isi prompt
    array  $context   // ['institusi_id'=>..., 'max_tokens'=>..., 'temperature'=>..., ...]
): AiOutcome;
```

**`AiOutcome`** membungkus: `->result` (`LlmResult`: `->text`, `->error`, `->modelVersion`, `failed()`), `->biaya`, dan `->interaksi` (`AiInteraksi`: `->provider`, `->model`, `->status`, `->tokens_out`).

**Routing per‑tugas & profil** (`config/ai.php`):

- `providers`: openai, anthropic, gemini, deepseek, **nvidia** (semua kompatibel‑OpenAI → memakai `OpenAiDriver`), dan `mock`.
- `profiles`: `produksi`, `simulasi`, `simulasi_nvidia` — memetakan `task → modelKey`.
- **Profil aktif** ditentukan berurutan: baris `ai_pengaturan` tenant → baris global (`institusi_id` NULL) → `config('ai.active_profile')` (env `AI_PROFILE`). Diubah dari UI **tanpa deploy**.
- **BYOK:** kredensial per‑tenant di tabel `ai_kredensial` (terenkripsi) diutamakan di atas env.
- **Budget guard:** bila `ai_kredensial.anggaran` diisi, total biaya `ai_interaksi` per tenant yang melewati anggaran → `AiBudgetException`.
- **Cross‑provider guard:** task `validator` wajib provider berbeda dari generator (dijamin oleh peta profil).

> **GOTCHA:** key model di `config/ai.php` **tidak boleh mengandung titik** (dot‑notation `config()` memecahnya). Pakai `gpt-oss-120b`, `gemini-flash`, dst. Nama model API asli (boleh bertitik) disimpan di nilai env.

Detail model & catatan tiap provider terekam di komentar `config/ai.php`.

---

## 7. Pipeline Generator RPS

**Aturan keras:** AI **tidak** membuat seluruh RPS sekaligus. Setiap tahap = satu panggilan AI terpisah dengan skema JSON sempit, dan tahap berikutnya baru jalan setelah tahap sebelumnya dikunci.

Urutan (`config/generator.php`): `cpmk → sub_cpmk → mingguan → penilaian`.

`App\Services\Generator\RpsGeneratorService`:

- `start(MataKuliah, $opts)` → buat `GenerateSession` (draf staging JSON per tahap).
- `generateStage($session, $stage)` → cek prasyarat + panggil AI + validasi grounding + (opsional) regenerasi otomatis; simpan draf.
- `acceptStage` / `rejectStage` / `pinStage` → kontrol checkpoint manusia (status: `accepted`/`edited`/`pinned` = terkunci).
- `commit($session)` → tulis draf ke entitas resmi (`Cpmk`, `SubCpmk`, `Indikator`, `RpsVersion`, `RpsMinggu`, `KomponenPenilaian`) dalam satu transaksi. Guard cegah commit ganda.

Prompt diambil via `PromptRepository` (override DB `prompt_template` per‑tenant/jenis MK → fallback `config/prompts.php`).

---

## 8. RAG & Grounding (Anti‑Halusinasi)

- **`EmbeddingService`** — `embed()`, `embedChunk()`, `search()`. Embedding disimpan JSON di `dokumen_chunk.embedding`; pencarian = cosine similarity di PHP. Provider NVIDIA wajib `input_type` (`query`/`passage`) + `truncate`, dan **tidak menerima** param `dimensions`.
- **`GroundingValidator::validate($teks, $context)`** — ekstrak klaim → cari bukti (`search`) → `judge` (task `validator`, lintas‑provider) → tetapkan status:
  - `grounded` → terima
  - `kontradiktif` → tolak
  - `tak_didukung` → **tolak** bila kategori ketat (`regulasi_nasional`/`akreditasi`/`asosiasi_profesi`), selain itu revisi otomatis 1×.
- Hasil ditulis ke `generate_session.catatan_validasi` dan tabel `ai_validasi`.

> Mengganti model embedding **wajib** meng‑embed ulang **semua** chunk (dimensi/ruang vektor berbeda). Skor cosine antar model berbeda → sesuaikan `AI_GROUNDING_MIN_SCORE`.

---

## 9. Daftar Endpoint

Semua ber‑prefix `/api/v1`. Ringkasan per modul (detail: [`routes/api.php`](routes/api.php)):

**Auth & RBAC**
```
POST   auth/login | auth/logout | auth/me | auth/profile | auth/password
GET/POST/PUT/DELETE  roles, users, institusi   (dijaga permission)
GET    rbac/katalog
```

**Pengaturan AI & Prompt**
```
GET/PUT  ai/pengaturan
POST     ai/asistif
GET      prompts/catalog
apiResource prompt-templates
GET/POST/PUT/DELETE  konfigurasi-aturan, template-rps
```

**Modul 0 — Onboarding & Dokumen Rujukan**
```
POST  onboarding/preview | onboarding/import
GET/POST  onboarding/mapping
apiResource badan-rujukan, kerangka-acuan
GET/POST/DELETE  dokumen-rujukan (+ /search, /{id}/reindex)
POST  pemenuhan-acuan/upsert ; butir-acuan
```

**Modul 1 — Peta Kurikulum & Master**
```
apiResource kurikulum, profil-lulusan, cpl, mata-kuliah, bahan-kajian, taksonomi
GET   kurikulum/{k}/matriks | matriks-bahan-kajian | matriks-mk-bahan-kajian | matriks-profil-lulusan | traceability
POST  .../link | .../suggest      DELETE .../link      (per matriks)
```

**Modul 2 — Generator & RPS**
```
apiResource generate-sessions  (index, store, show, destroy)
POST  generate-sessions/{s}/generate | accept | reject | pin | commit | audit
GET   rps-versions | rps-versions/{r} | /traceability | /cetak | /docx
DELETE rps-versions/{r}
POST  rps-versions/{r}/audit ; rps/ai/chat
```

**Modul 3/4 — Overlap & Persetujuan**
```
GET/POST/PUT  validasi-overlap (+ /pindai, /{id}/analisis, /review)
GET   persetujuan ; rps-versions/{r}/riwayat-persetujuan
POST  rps-versions/{r}/ajukan | setujui | revisi | tarik
```

**Modul 6/8 — OBAEI & Tata Kelola**
```
GET   obaei/agregasi
GET/POST/PUT/DELETE  target-cpl, capaian-mahasiswa, evaluasi-cpl (+ /analisis, /finalisasi), tindak-lanjut
GET   governance/ringkasan | penggunaan | audit-log | notifikasi
POST  governance/notifikasi/{n}/dibaca
```

---

## 10. Autentikasi & RBAC

- Login (`POST auth/login`) mengembalikan **bearer token** (Sanctum). Frontend menyimpannya di cookie `rps_token` (HttpOnly, Secure) dan mengirimnya server‑side.
- Endpoint di dalam grup `auth:sanctum` dan sebagian dijaga `permission:*` (spatie). Contoh: `role.view`, `user.manage`, `prodi.manage`.
- Endpoint data OBE dibiarkan terbuka selama pengembangan (mengikuti pola Survey Service); pengetatan/OIDC ditunda ke fase ekosistem.

---

## 11. Antrean & Job

Driver antrean `database`. **Aksi berat wajib asinkron** (Cloudflare Tunnel time‑out ±100 dtk). Contoh: `IngestDokumenJob` memproses unggahan dokumen rujukan → ekstraksi → chunk → embedding. Worker berjalan di kontainer `api` (supervisord). Frontend melakukan polling status (`pending` → `indexed`/`error`).

---

## 12. Pengujian

```bash
php artisan test          # atau: ./vendor/bin/phpunit
php84 -l app/.../File.php  # cek sintaks cepat
```

---

## 13. Catatan Penting (Gotchas)

- **Key model tanpa titik** di `config/ai.php` (lihat §6).
- **Provider NVIDIA** butuh `input_type` + `truncate`, tolak `dimensions`.
- **Ganti model embedding** → re‑embed semua chunk.
- **`AI_FALLBACK_MOCK=true`** menyamarkan kegagalan AI menjadi output mock (JSON echo). Set `false` bila ingin kegagalan terlihat.
- **Dokumen rujukan** terikat tenant lewat `institusi_id` yang **NOT NULL** (belum ada dokumen global di level DB) — pastikan `institusi_id` dokumen = tenant kurikulum agar terambil saat search.
- Env di produksi hanya yang terdaftar di compose (lihat §3).
