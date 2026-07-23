"use server";

import { revalidatePath } from "next/cache";
import { apiGet, apiPost, apiPut, apiDelete, type ApiResult, type Referensi } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

function base(kurikulumId: string) {
  return `/kurikulum/${kurikulumId}/mata-kuliah`;
}

function toInt(v: FormDataEntryValue | null): number | null {
  const s = (v as string) ?? "";
  if (s === "") return null;
  const n = Number(s);
  return Number.isFinite(n) ? n : null;
}

type ReferensiItem = { tipe: "utama" | "pendukung"; sitasi: string };

/** Parse hidden field referensi_json (dari ReferensiEditor) menjadi array bersih. */
function parseReferensi(fd: FormData): ReferensiItem[] {
  const raw = (fd.get("referensi_json") as string) || "";
  if (!raw.trim()) return [];
  try {
    const arr = JSON.parse(raw) as ReferensiItem[];
    if (!Array.isArray(arr)) return [];
    return arr
      .map((r) => ({
        tipe: r.tipe === "pendukung" ? "pendukung" : "utama",
        sitasi: String(r.sitasi ?? "").trim(),
      }))
      .filter((r) => r.sitasi !== "") as ReferensiItem[];
  } catch {
    return [];
  }
}

/** Ganti-total referensi satu MK (dipakai setelah MK tersimpan). */
async function syncReferensi(institusiId: number, kodeMk: string, items: ReferensiItem[]) {
  await apiPost("/referensi/sync", { institusi_id: institusiId, kode_mk: kodeMk, items });
}

/** Muat referensi satu MK untuk mengisi editor saat modal dibuka. */
export async function listReferensi(institusiId: number, kodeMk: string): Promise<Referensi[]> {
  try {
    const res = await apiGet<{ data: Referensi[] }>("/referensi", { institusi_id: institusiId, kode_mk: kodeMk });
    return res.data ?? [];
  } catch {
    return [];
  }
}

/** Saran pustaka via AI (DRAFT — wajib diverifikasi dosen). */
export async function suggestReferensi(input: {
  nama: string;
  jenis?: string;
  sks?: number | null;
  deskripsi?: string;
  kode_mk?: string;
}): Promise<ApiResult<ReferensiItem[]>> {
  return apiPost<ReferensiItem[]>("/referensi/suggest", {
    institusi_id: DEFAULT_INSTITUSI,
    nama: input.nama,
    jenis: input.jenis ?? null,
    sks: input.sks ?? null,
    deskripsi: input.deskripsi ?? null,
    kode_mk: input.kode_mk ?? null,
  });
}

export async function createMataKuliah(formData: FormData) {
  const kurikulumId = formData.get("kurikulum_id") as string;
  const institusiId = toInt(formData.get("institusi_id")) ?? DEFAULT_INSTITUSI;
  const kodeMk = formData.get("kode_mk") as string;
  const body = {
    institusi_id: institusiId,
    kurikulum_id: Number(kurikulumId),
    kode_mk: kodeMk,
    nama: formData.get("nama") as string,
    jenis_mk: (formData.get("jenis_mk") as string) || "murni",
    pola: (formData.get("pola") as string) || "reguler",
    jumlah_minggu: toInt(formData.get("jumlah_minggu")),
    sifat: (formData.get("sifat") as string) || null,
    rumpun: (formData.get("rumpun") as string) || null,
    deskripsi_singkat: (formData.get("deskripsi_singkat") as string) || null,
    sks_teori: toInt(formData.get("sks_teori")),
    sks_praktik: toInt(formData.get("sks_praktik")),
    semester: toInt(formData.get("semester")),
    prasyarat_kode: (formData.get("prasyarat_kode") as string) || null,
  };
  const res = await apiPost("/mata-kuliah", body);
  if (res.ok && kodeMk) {
    await syncReferensi(institusiId, kodeMk, parseReferensi(formData));
  }
  revalidatePath(base(kurikulumId));
  return res;
}

export async function updateMataKuliah(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const institusiId = toInt(formData.get("institusi_id")) ?? DEFAULT_INSTITUSI;
  const kodeMk = formData.get("kode_mk") as string;
  const body = {
    institusi_id: institusiId,
    kode_mk: kodeMk,
    nama: formData.get("nama") as string,
    jenis_mk: (formData.get("jenis_mk") as string) || "murni",
    pola: (formData.get("pola") as string) || "reguler",
    jumlah_minggu: toInt(formData.get("jumlah_minggu")),
    sifat: (formData.get("sifat") as string) || null,
    rumpun: (formData.get("rumpun") as string) || null,
    deskripsi_singkat: (formData.get("deskripsi_singkat") as string) || null,
    sks_teori: toInt(formData.get("sks_teori")),
    sks_praktik: toInt(formData.get("sks_praktik")),
    semester: toInt(formData.get("semester")),
    prasyarat_kode: (formData.get("prasyarat_kode") as string) || null,
  };
  const res = await apiPut(`/mata-kuliah/${id}`, body);
  if (res.ok && kodeMk) {
    await syncReferensi(institusiId, kodeMk, parseReferensi(formData));
    // Jika kode MK diubah, pindahkan referensi ke kode baru (sudah dilakukan di
    // atas) lalu bersihkan sisa yatim pada kode lama.
    const kodeMkLama = (formData.get("kode_mk_lama") as string) || "";
    if (kodeMkLama && kodeMkLama !== kodeMk) {
      await syncReferensi(institusiId, kodeMkLama, []);
    }
  }
  revalidatePath(base(kurikulumId));
  return res;
}

export async function deleteMataKuliah(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const res = await apiDelete(`/mata-kuliah/${id}`);
  revalidatePath(base(kurikulumId));
  return res;
}

/** Tautkan/lepas satu sel matriks CPL × Mata Kuliah (agar CPL tak yatim). */
export async function toggleMkCplLink(
  kurikulumId: number,
  kodeMk: string,
  cplId: number,
  active: boolean,
) {
  const path = `/kurikulum/${kurikulumId}/matriks/link`;
  const body = { kode_mk: kodeMk, cpl_id: cplId };
  const res = active ? await apiPost(path, body) : await apiDelete(path, body);
  revalidatePath(`/kurikulum/${kurikulumId}`);
  return res;
}

/** Tautkan/lepas satu sel matriks Bahan Kajian × Mata Kuliah (acuan peninjauan). */
export async function toggleMkBahanKajianLink(
  kurikulumId: number,
  kodeMk: string,
  bahanKajianId: number,
  active: boolean,
) {
  const path = `/kurikulum/${kurikulumId}/matriks-mk-bahan-kajian/link`;
  const body = { kode_mk: kodeMk, bahan_kajian_id: bahanKajianId };
  const res = active ? await apiPost(path, body) : await apiDelete(path, body);
  revalidatePath(`/kurikulum/${kurikulumId}`);
  return res;
}

/** Saran AI matriks CPL × Mata Kuliah (usulan, user tetap memutuskan). */
export async function suggestMkCplLinks(kurikulumId: number) {
  return apiPost<{ links: { kode_mk: string; cpl_id: number }[] }>(
    `/kurikulum/${kurikulumId}/matriks/suggest`,
    {},
  );
}

/** Saran AI matriks Bahan Kajian × Mata Kuliah (usulan, user tetap memutuskan). */
export async function suggestMkBahanKajianLinks(kurikulumId: number) {
  return apiPost<{ links: { kode_mk: string; bahan_kajian_id: number }[] }>(
    `/kurikulum/${kurikulumId}/matriks-mk-bahan-kajian/suggest`,
    {},
  );
}
