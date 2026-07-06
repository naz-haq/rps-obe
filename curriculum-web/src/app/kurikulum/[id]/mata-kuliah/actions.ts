"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete } from "@/lib/api";

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

export async function createMataKuliah(formData: FormData) {
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    institusi_id: toInt(formData.get("institusi_id")) ?? DEFAULT_INSTITUSI,
    kurikulum_id: Number(kurikulumId),
    kode_mk: formData.get("kode_mk") as string,
    nama: formData.get("nama") as string,
    jenis_mk: (formData.get("jenis_mk") as string) || null,
    sifat: (formData.get("sifat") as string) || null,
    rumpun: (formData.get("rumpun") as string) || null,
    deskripsi_singkat: (formData.get("deskripsi_singkat") as string) || null,
    sks_teori: toInt(formData.get("sks_teori")),
    sks_praktik: toInt(formData.get("sks_praktik")),
    semester: toInt(formData.get("semester")),
    prasyarat_kode: (formData.get("prasyarat_kode") as string) || null,
  };
  const res = await apiPost("/mata-kuliah", body);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function updateMataKuliah(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    institusi_id: toInt(formData.get("institusi_id")) ?? DEFAULT_INSTITUSI,
    kode_mk: formData.get("kode_mk") as string,
    nama: formData.get("nama") as string,
    jenis_mk: (formData.get("jenis_mk") as string) || null,
    sifat: (formData.get("sifat") as string) || null,
    rumpun: (formData.get("rumpun") as string) || null,
    deskripsi_singkat: (formData.get("deskripsi_singkat") as string) || null,
    sks_teori: toInt(formData.get("sks_teori")),
    sks_praktik: toInt(formData.get("sks_praktik")),
    semester: toInt(formData.get("semester")),
    prasyarat_kode: (formData.get("prasyarat_kode") as string) || null,
  };
  const res = await apiPut(`/mata-kuliah/${id}`, body);
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
