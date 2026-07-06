/**
 * Klien API server-side (server components & server actions).
 * Fetch server-to-server ke Curriculum Service — tanpa CORS.
 * Menyertakan token Sanctum (cookie httpOnly) di setiap permintaan.
 */
import { cookies } from "next/headers";

export const API_BASE_URL = process.env.API_BASE_URL ?? "http://127.0.0.1:8100/api/v1";

/**
 * Basis URL untuk tautan yang dibuka langsung oleh BROWSER (unduh/cetak).
 * Path relatif ini diteruskan Next ke backend lewat rewrites di next.config.ts,
 * sehingga tetap berfungsi saat aplikasi diakses via tunnel (satu origin).
 */
export const BACKEND_PROXY = "/backend/api/v1";

export type Paginated<T> = {
  data: T[];
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
};

export type Single<T> = { data: T };

// ---- Tipe entitas (cocok dengan JsonResource backend) ----
export type Kurikulum = {
  id: number;
  ulid?: string;
  institusi_id: number;
  kode: string | null;
  nama: string;
  tahun: string;
  status: "draft" | "berlaku" | "arsip";
  tanggal_berlaku?: string | null;
  mata_kuliah_count?: number;
  cpl_count?: number;
  created_at?: string;
};

export type Cpl = {
  id: number;
  institusi_id: number;
  kurikulum_id: number | null;
  kode: string;
  deskripsi: string;
  aspek: string | null;
  level_kkni: string | null;
  sumber: string | null;
};

export type ProfilLulusan = {
  id: number;
  institusi_id: number;
  kurikulum_id: number | null;
  kode: string;
  deskripsi: string;
};

export type MataKuliah = {
  id: number;
  ulid?: string;
  institusi_id: number;
  institusi_nama?: string | null;
  kurikulum_id: number | null;
  kode_mk: string;
  nama: string;
  jenis_mk: string;
  sifat: string | null;
  rumpun: string | null;
  deskripsi_singkat: string | null;
  sks_teori: number;
  sks_praktik: number;
  sks: number;
  semester: number | null;
  prodi_kode?: string | null;
  prasyarat_kode?: string | null;
  estimasi_waktu?: EstimasiWaktu;
};

export type EstimasiWaktu = {
  tm_menit: number;
  pt_menit: number;
  bm_menit: number;
  praktik_menit: number;
  total_menit: number;
  teks: string;
};

export type BadanRujukan = {
  id: number;
  institusi_id: number | null;
  nama: string;
  jenis: "asosiasi" | "akreditasi" | "pemerintah" | "institusi";
  disiplin: string | null;
  dokumen_count?: number;
};

export type DokumenRujukan = {
  id: number;
  institusi_id: number;
  badan_rujukan_id: number | null;
  badan_rujukan?: string | null;
  jenis: "kpt" | "asosiasi" | "akreditasi" | "template_rps";
  judul: string | null;
  file_asal: string | null;
  status_indexing: "pending" | "indexed" | "error";
  chunk_count?: number;
  created_at?: string;
};

// ---- Modul 3: Validator Overlap ----
export type ValidasiOverlapStatus = "overlap" | "aman" | "perlu_review";

export type MkTerlibat = {
  kode_mk: string;
  fokus_spesifik: string | null;
};

export type ValidasiOverlap = {
  id: number;
  institusi_id: number;
  keterampilan_id: number;
  keterampilan?: {
    id: number;
    deskripsi: string | null;
    domain: string | null;
    bahan_kajian: string | null;
  } | null;
  mk_terlibat: MkTerlibat[];
  jumlah_mk: number;
  status: ValidasiOverlapStatus;
  analisis: string | null;
  rekomendasi: string | null;
  reviewed_by: number | null;
  created_at?: string;
  updated_at?: string;
};

export type PindaiOverlapRingkasan = {
  diperiksa: number;
  overlap: number;
  baru: number;
  dibersihkan: number;
};

export type KerangkaAcuan = {
  id: number;
  badan_rujukan_id: number;
  badan_rujukan?: string | null;
  dokumen_id: number | null;
  nama: string;
  versi: string | null;
  tanggal_berlaku: string | null;
  butir_count?: number;
  created_at?: string;
};

export type ButirKategori =
  | "profil_lulusan"
  | "cpl"
  | "bahan_kajian"
  | "kriteria_akreditasi"
  | "struktur"
  | "aturan";

export type PemenuhanStatus = "terpenuhi" | "sebagian" | "belum" | "tidak_relevan";

export type ButirAcuan = {
  id: number;
  kerangka_acuan_id: number;
  parent_id: number | null;
  kategori: ButirKategori;
  kode: string | null;
  deskripsi: string;
  wajib: boolean;
  urutan: number;
  status: PemenuhanStatus;
  catatan: string | null;
  rekomendasi_ai: boolean;
};

export type ChecklistRingkasan = {
  total: number;
  terpenuhi: number;
  sebagian: number;
  belum: number;
  tidak_relevan: number;
  persen: number;
};

export type ChecklistDetail = {
  kerangka: KerangkaAcuan;
  butir: ButirAcuan[];
  ringkasan: ChecklistRingkasan;
};

export type MatriksLink = { kode_mk: string; cpl_id: number; bobot: number | null };
export type Matriks = { mata_kuliah: MataKuliah[]; cpl: Cpl[]; links: MatriksLink[] };
export type TracePeta = {
  cpl_id: number;
  kode: string;
  deskripsi: string;
  mata_kuliah: { kode_mk: string; nama: string | null; bobot: number | null }[];
  yatim: boolean;
};
export type Traceability = {
  peta: TracePeta[];
  cpl_yatim: string[];
  total_cpl: number;
  total_mk: number;
};

export type BahanKajian = {
  id: number;
  institusi_id: number;
  kurikulum_id: number | null;
  nama: string;
  deskripsi: string | null;
};

export type MatriksBkLink = { cpl_id: number; bahan_kajian_id: number };
export type MatriksBahanKajian = { bahan_kajian: BahanKajian[]; cpl: Cpl[]; links: MatriksBkLink[] };

export type MatriksMkBkLink = { kode_mk: string; bahan_kajian_id: number };
export type MatriksMkBahanKajian = {
  mata_kuliah: MataKuliah[];
  bahan_kajian: BahanKajian[];
  links: MatriksMkBkLink[];
};

export type MatriksPlLink = { profil_lulusan_id: number; cpl_id: number };
export type MatriksProfilLulusan = { profil_lulusan: ProfilLulusan[]; cpl: Cpl[]; links: MatriksPlLink[] };

export type GenerateSession = {  id: number;
  institusi_id: number;
  mk_id: number;
  kode_mk?: string | null;
  nama_mk?: string | null;
  sumber: string;
  tahap: string;
  status: string;
  status_bagian: Record<string, string> | null;
  draf: Record<string, unknown> | null;
  catatan_validasi: Record<string, unknown> | null;
  rps_version_id: number | null;
  created_at?: string;
  updated_at?: string;
};

export type RpsVersion = {
  id: number;
  ulid?: string;
  institusi_id: number;
  kode_mk: string;
  versi: number;
  status: string;
  bahasa: string;
  kode_dokumen?: string | null;
  created_by?: number | null;
  koordinator_mk?: number | null;
  approved_by?: number | null;
  submitted_at?: string | null;
  approved_at?: string | null;
  catatan_review?: string | null;
  tanggal_penyusunan?: string | null;
  minggu_count?: number;
  komponen_count?: number;
  created_at?: string;
};

export type RpsApprovalLog = {
  id: number;
  rps_version_id: number;
  aksi: "ajukan" | "setujui" | "revisi" | "tarik";
  dari_status: string | null;
  ke_status: string;
  catatan: string | null;
  actor_id: number | null;
  actor_nama: string | null;
  created_at: string;
};

// ---- Modul 6 — OBAEI ----
export type TargetCpl = {
  id: number;
  cpl_id: number;
  cpl?: { id: number; kode: string; deskripsi: string };
  angkatan: string | null;
  ambang_nilai: number | null;
  persentase_target: number | null;
  created_at?: string;
};

export type CapaianMahasiswa = {
  id: number;
  kode_mk: string;
  sub_cpmk_id: number | null;
  sub_cpmk?: string | null;
  cpmk_id: number | null;
  cpmk?: string | null;
  angkatan: string | null;
  jumlah_mahasiswa: number | null;
  nilai_rata_rata: number | null;
  persentase_capaian_minimal: number | null;
  created_at?: string;
};

export type ObaeiStatus = "tercapai" | "belum" | "tanpa_data" | "tanpa_target";

export type ObaeiCplRow = {
  cpl_id: number;
  kode: string;
  deskripsi: string;
  target_persen: number | null;
  ambang_nilai: number | null;
  capaian_persen: number | null;
  jumlah_mahasiswa: number;
  jumlah_komponen: number;
  status: ObaeiStatus;
  selisih: number | null;
};

export type ObaeiRingkasan = {
  total_cpl: number;
  tercapai: number;
  belum: number;
  tanpa_data: number;
  persen_tercapai: number;
};

export type ObaeiAgregasi = { ringkasan: ObaeiRingkasan; cpl: ObaeiCplRow[] };

export type TindakLanjut = {
  id: number;
  evaluasi_cpl_id: number;
  sub_cpmk_id: number | null;
  sub_cpmk?: string | null;
  catatan: string;
  prioritas: "tinggi" | "sedang" | "rendah" | null;
  status: string | null;
  created_at?: string;
};

export type EvaluasiCpl = {
  id: number;
  cpl_id: number;
  cpl?: { id: number; kode: string; deskripsi: string };
  periode: string | null;
  ringkasan_naratif: string | null;
  status: "draft" | "final";
  tindak_lanjut?: TindakLanjut[];
  tindak_lanjut_count?: number;
  created_at?: string;
  updated_at?: string;
};

// ---- Modul 8 — Tata Kelola & Monitoring ----
export type GovRingkasan = {
  periode_hari: number;
  total_interaksi: number;
  sukses: number;
  gagal: number;
  success_rate: number;
  tokens_in: number;
  tokens_out: number;
  tokens_total: number;
  total_biaya: number;
  total_audit: number;
  notifikasi_unread: number;
};

export type GovPenggunaan = {
  per_mode: { mode: string; jumlah: number; tokens: number; biaya: number }[];
  per_model: { model: string; jumlah: number; tokens: number; biaya: number }[];
  per_hari: { tanggal: string; jumlah: number; tokens: number; biaya: number }[];
  per_status: { status: string; jumlah: number }[];
};

export type AuditLog = {
  id: number;
  institusi_id: number | null;
  user_id: number | null;
  actor_nama: string | null;
  action: string;
  entity: string | null;
  entity_id: number | null;
  meta: Record<string, unknown> | null;
  created_at: string;
};

export type Notifikasi = {
  id: number;
  institusi_id: number;
  user_id: number | null;
  jenis: string;
  konten: string;
  status: "unread" | "read";
  created_at: string;
};

export type RpsMinggu = {
  minggu_ke: number;
  sub_cpmk: string | null;
  sub_cpmk_deskripsi: string | null;
  sub_cpmk_bloom: string | null;
  cpmk: string | null;
  cpmk_deskripsi: string | null;
  indikator: string | null;
  kriteria_penilaian: string | null;
  metode_pembelajaran: string | null;
  bentuk_luring: string | null;
  bentuk_daring: string | null;
  pengalaman_belajar: string | null;
  materi_pustaka: string | null;
  estimasi_waktu: EstimasiWaktu | null;
  bobot_penilaian: number | null;
};
export type RpsKomponen = {
  nama: string;
  jenis: string | null;
  instrumen?: string | null;
  bobot_persen: number | null;
  sub_cpmk: string | null;
  sub_cpmk_deskripsi: string | null;
  cpmk: string | null;
  cpmk_deskripsi: string | null;
  minggu_ke: number | null;
  rubrik?: RpsRubrik | null;
};
export type RpsRubrikKriteria = {
  kriteria: string;
  bobot: number | string | null;
  deskriptor: string[] | null;
};
export type RpsRubrik = {
  jenis: string;
  jumlah_level_skala: number;
  label_skala: string[] | null;
  kriteria: RpsRubrikKriteria[];
};
export type RpsKonteksCpmk = { kode: string; deskripsi: string | null; bloom: string | null; kontribusi_persen?: number };
export type RpsKonteksSubCpmk = { kode: string; deskripsi: string | null; cpmk: string | null; bloom: string | null; kontribusi_persen?: number };
export type RpsKonteksBK = { nama: string; deskripsi: string | null; keterampilan: string[] };
export type RpsKonteksMatriksBaris = {
  sub_cpmk: string;
  cpmk: string | null;
  bobot_per_cpl: Record<string, number | null>;
  bobot_nilai: number | null;
  jumlah_minggu: number;
  kontribusi_persen: number;
};
export type RpsKonteksCpmkKontribusi = {
  cpmk: string;
  jumlah_minggu: number;
  kontribusi_persen: number;
};
export type RpsKonteks = {
  universitas: { nama: string } | null;
  fakultas: { nama: string } | null;
  prodi: { nama: string; kode?: string | null } | null;
  pengampu: { nama: string; nidn: string; peran: string }[];
  prasyarat: { kode: string; nama: string | null } | null;
  bahan_kajian: RpsKonteksBK[];
  pustaka_utama: string[];
  pustaka_pendukung: string[];
  cpmk_list: RpsKonteksCpmk[];
  sub_cpmk_list: RpsKonteksSubCpmk[];
  matriks_korelasi: {
    cpl: { id: number; kode: string }[];
    baris: RpsKonteksMatriksBaris[];
    total_minggu: number;
    cpmk_kontribusi: RpsKonteksCpmkKontribusi[];
  };
};
export type RpsDetail = {
  rps: RpsVersion;
  minggu: RpsMinggu[];
  komponen: RpsKomponen[];
  konteks?: RpsKonteks;
};
export type RpsRantai = {
  sub_cpmk: string;
  deskripsi: string | null;
  cpmk: string | null;
  cpl: string[];
  minggu: number[];
};
export type RpsTraceability = {
  kode_mk: string;
  versi: number;
  rantai: RpsRantai[];
  cpl_diampu: string[];
};

export type AiModelInfo = {
  key: string;
  provider: string;
  model: string;
  pricing: { input: number; output: number } | null;
};
export type AiPengaturan = {
  profil_aktif: string;
  default_env: string;
  global_tersimpan: string | null;
  tenant_tersimpan: string | null;
  profil_tersedia: string[];
  profiles: Record<string, Record<string, string>>;
  providers: string[];
  models: AiModelInfo[];
};

// ---- Taksonomi master (Bloom/Krathwohl/Dave + kata kerja operasional) ----
export type TaksonomiDomain = "kognitif" | "afektif" | "psikomotorik";
export type TaksonomiKerangka = "bloom_anderson" | "krathwohl" | "dave" | "simpson";

export type Taksonomi = {
  id: number;
  institusi_id: number | null;
  domain: TaksonomiDomain;
  kerangka: TaksonomiKerangka;
  kode: string;
  nama: string;
  level: number;
  deskripsi: string | null;
  kata_kerja: string[];
  created_at?: string;
  updated_at?: string;
};

// ---- Konfigurasi Aturan (diisi manual, otoritatif) ----
export type BobotKomponen = { nama: string; bobot: number };

export type KonfigurasiAturan = {
  id: number;
  institusi_id: number;
  jenis_aturan: "jumlah_minggu" | "bobot_teori" | "bobot_praktikum" | "konversi_sks";
  nilai: Record<string, unknown>;
  badan_rujukan_id: number | null;
  referensi_dokumen_id: number | null;
  referensi_halaman: number | null;
  created_at?: string;
  updated_at?: string;
};

// ---- Template dokumen RPS (cetak seragam) ----
export type TemplateRps = {
  id: number;
  institusi_id: number;
  nama: string;
  keterangan: string | null;
  format: string | null;
  berkas_nama_asli: string | null;
  is_active: boolean;
  created_at?: string;
};

// ---- Prompt AI terpusat ----
export type PromptTemplate = {
  id: number;
  jenis_output: string;
  jenis_mk: string | null;
  institusi_id: number | null;
  sistem_prompt: string;
  skema_output: string | null;
  versi: number | null;
  aktif: boolean;
  created_at?: string;
  updated_at?: string;
};
export type PromptSlot = {
  slot: string;
  label: string;
  group: string;
  default_system: string;
  default_schema: string;
  sumber_efektif: "default" | "override";
  override: PromptTemplate | null;
};

// ---- Auth & RBAC ----
export type RolePermissionItem = { name: string; label: string };
export type RbacGroup = { key: string; label: string; permissions: RolePermissionItem[] };
export type RbacKatalog = { groups: RbacGroup[] };

export type RoleData = {
  id: number;
  name: string;
  label: string;
  deskripsi: string | null;
  bawaan: boolean;
  permissions: string[];
  users_count: number;
  created_at?: string;
};

export type UserAccount = {
  id: number;
  name: string;
  email: string | null;
  nidn: string | null;
  jabatan: string | null;
  is_active: boolean;
  institusi_id: number | null;
  institusi: { id: number; nama: string; jenis: string } | null;
  roles: string[];
  permissions: string[];
  created_at?: string;
};

export type InstitusiRingkas = { id: number; nama: string; jenis: string };

export type InstitusiData = {
  id: number;
  kode: string | null;
  nama: string;
  jenis: string;
  parent_id: number | null;
  parent_nama: string | null;
  asosiasi_profesi: string | null;
  dosen_count: number;
  mata_kuliah_count: number;
};

// ---- Helper fetch ----
type Query = Record<string, string | number | undefined | null>;

function buildUrl(path: string, query?: Query): string {
  const url = new URL(API_BASE_URL + path);
  if (query) {
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, String(v));
    }
  }
  return url.toString();
}

async function authHeaders(): Promise<Record<string, string>> {
  try {
    const token = (await cookies()).get("rps_token")?.value;
    return token ? { Authorization: `Bearer ${token}` } : {};
  } catch {
    return {};
  }
}

export async function apiGet<T>(path: string, query?: Query): Promise<T> {
  const res = await fetch(buildUrl(path, query), {
    headers: { Accept: "application/json", ...(await authHeaders()) },
    cache: "no-store",
  });
  if (!res.ok) throw new Error(`GET ${path} gagal (${res.status})`);
  return res.json() as Promise<T>;
}

export type ApiResult<T = unknown> = { ok: boolean; status: number; data?: T; message?: string };

async function send<T>(method: string, path: string, body?: unknown): Promise<ApiResult<T>> {
  const res = await fetch(buildUrl(path), {
    method,
    headers: { "Content-Type": "application/json", Accept: "application/json", ...(await authHeaders()) },
    cache: "no-store",
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    /* respons kosong */
  }
  const obj = (json ?? {}) as Record<string, unknown>;
  return {
    ok: res.ok,
    status: res.status,
    data: (obj.data as T) ?? (json as T),
    message: (obj.message as string) ?? (res.ok ? undefined : `Gagal (${res.status})`),
  };
}

export const apiPost = <T>(path: string, body?: unknown) => send<T>("POST", path, body);
export const apiPut = <T>(path: string, body?: unknown) => send<T>("PUT", path, body);
export const apiDelete = <T>(path: string, body?: unknown) => send<T>("DELETE", path, body);

export async function apiPostForm<T>(path: string, form: FormData): Promise<ApiResult<T>> {
  const res = await fetch(buildUrl(path), {
    method: "POST",
    headers: { Accept: "application/json", ...(await authHeaders()) },
    cache: "no-store",
    body: form,
  });
  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    /* respons kosong */
  }
  const obj = (json ?? {}) as Record<string, unknown>;
  return {
    ok: res.ok,
    status: res.status,
    data: (obj.data as T) ?? (json as T),
    message: (obj.message as string) ?? (res.ok ? undefined : `Gagal (${res.status})`),
  };
}
