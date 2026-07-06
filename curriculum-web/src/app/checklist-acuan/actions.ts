"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;
const LIST = "/checklist-acuan";

export async function createKerangka(formData: FormData) {
  const body = {
    badan_rujukan_id: Number(formData.get("badan_rujukan_id")),
    nama: formData.get("nama") as string,
    versi: (formData.get("versi") as string) || null,
    tanggal_berlaku: (formData.get("tanggal_berlaku") as string) || null,
  };
  const res = await apiPost("/kerangka-acuan", body);
  revalidatePath(LIST);
  return res;
}

export async function updateKerangka(formData: FormData) {
  const id = formData.get("id") as string;
  const body = {
    badan_rujukan_id: Number(formData.get("badan_rujukan_id")),
    nama: formData.get("nama") as string,
    versi: (formData.get("versi") as string) || null,
    tanggal_berlaku: (formData.get("tanggal_berlaku") as string) || null,
  };
  const res = await apiPut(`/kerangka-acuan/${id}`, body);
  revalidatePath(LIST);
  revalidatePath(`${LIST}/${id}`);
  return res;
}

export async function deleteKerangka(formData: FormData) {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/kerangka-acuan/${id}`);
  revalidatePath(LIST);
  return res;
}

// ---- Butir ----
export async function createButir(formData: FormData) {
  const kerangkaId = formData.get("kerangka_id") as string;
  const body = {
    kategori: formData.get("kategori") as string,
    kode: (formData.get("kode") as string) || null,
    deskripsi: formData.get("deskripsi") as string,
    wajib: formData.get("wajib") === "on" || formData.get("wajib") === "1",
  };
  const res = await apiPost(`/kerangka-acuan/${kerangkaId}/butir`, body);
  revalidatePath(`${LIST}/${kerangkaId}`);
  return res;
}

export async function updateButir(formData: FormData) {
  const id = formData.get("id") as string;
  const kerangkaId = formData.get("kerangka_id") as string;
  const body = {
    kategori: formData.get("kategori") as string,
    kode: (formData.get("kode") as string) || null,
    deskripsi: formData.get("deskripsi") as string,
    wajib: formData.get("wajib") === "on" || formData.get("wajib") === "1",
  };
  const res = await apiPut(`/butir-acuan/${id}`, body);
  revalidatePath(`${LIST}/${kerangkaId}`);
  return res;
}

export async function deleteButir(formData: FormData) {
  const id = formData.get("id") as string;
  const kerangkaId = formData.get("kerangka_id") as string;
  const res = await apiDelete(`/butir-acuan/${id}`);
  revalidatePath(`${LIST}/${kerangkaId}`);
  return res;
}

// ---- Pemenuhan (status per butir) ----
export async function setPemenuhan(formData: FormData) {
  const kerangkaId = formData.get("kerangka_id") as string;
  const body = {
    institusi_id: DEFAULT_INSTITUSI,
    butir_acuan_id: Number(formData.get("butir_acuan_id")),
    status: formData.get("status") as string,
    catatan: (formData.get("catatan") as string) || null,
  };
  const res = await apiPost("/pemenuhan-acuan/upsert", body);
  revalidatePath(`${LIST}/${kerangkaId}`);
  return res;
}
