"use server";

import { revalidatePath } from "next/cache";
import { apiPostForm, apiDelete, apiPost, apiPatch } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;
const PATH = "/dokumen-rujukan";

export async function uploadDokumen(formData: FormData) {
  const file = formData.get("file");
  if (!(file instanceof File) || file.size === 0) {
    return { ok: false, status: 422, message: "Pilih berkas terlebih dahulu." };
  }
  const form = new FormData();
  form.set("institusi_id", String(DEFAULT_INSTITUSI));
  form.set("jenis", (formData.get("jenis") as string) || "kpt");
  const judul = (formData.get("judul") as string) || "";
  if (judul) form.set("judul", judul);
  const badan = (formData.get("badan_rujukan_id") as string) || "";
  if (badan) form.set("badan_rujukan_id", badan);
  form.set("sumber_konten", formData.get("sumber_konten") === "1" ? "1" : "0");
  form.set("file", file);

  const res = await apiPostForm("/dokumen-rujukan", form);
  revalidatePath(PATH);
  return res;
}

export async function reindexDokumen(formData: FormData) {
  const id = formData.get("id") as string;
  const res = await apiPost(`/dokumen-rujukan/${id}/reindex`);
  revalidatePath(PATH);
  return res;
}

// Toggle peran AI dokumen: sumber keilmuan (membentuk hasil generate & bukti
// grounding) vs rujukan format saja (tidak dipakai sebagai sumber substansi).
export async function toggleSumberKonten(formData: FormData) {
  const id = formData.get("id") as string;
  const next = formData.get("next") === "1";
  const res = await apiPatch(`/dokumen-rujukan/${id}`, { sumber_konten: next });
  revalidatePath(PATH);
  return res;
}

export async function deleteDokumen(formData: FormData) {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/dokumen-rujukan/${id}`);
  revalidatePath(PATH);
  return res;
}

// ---- Badan Rujukan ----
export async function createBadan(formData: FormData) {
  const body = {
    institusi_id: DEFAULT_INSTITUSI,
    nama: formData.get("nama") as string,
    jenis: (formData.get("jenis") as string) || "asosiasi",
    disiplin: (formData.get("disiplin") as string) || null,
  };
  const res = await apiPost("/badan-rujukan", body);
  revalidatePath(PATH);
  return res;
}

export async function deleteBadan(formData: FormData) {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/badan-rujukan/${id}`);
  revalidatePath(PATH);
  return res;
}
