"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, type ApiResult } from "@/lib/api";

const PATH = "/prodi";

function buildBody(formData: FormData) {
  const jenis = (formData.get("jenis") as string) || "prodi";
  const parentRaw = formData.get("parent_id") as string | null;
  return {
    nama: (formData.get("nama") as string) || "",
    jenis,
    parent_id: jenis === "prodi" && parentRaw ? Number(parentRaw) : null,
    kode: (formData.get("kode") as string) || null,
    asosiasi_profesi: (formData.get("asosiasi_profesi") as string) || null,
    jenjang: (formData.get("jenjang") as string) || null,
    gelar: (formData.get("gelar") as string) || null,
    akreditasi: (formData.get("akreditasi") as string) || null,
    nilai_institusi: (formData.get("nilai_institusi") as string) || null,
  };
}

export async function createInstitusi(formData: FormData): Promise<ApiResult> {
  const res = await apiPost("/institusi", buildBody(formData));
  revalidatePath(PATH);
  return res;
}

export async function updateInstitusi(formData: FormData): Promise<ApiResult> {
  const id = formData.get("id") as string;
  const res = await apiPut(`/institusi/${id}`, buildBody(formData));
  revalidatePath(PATH);
  return res;
}

export async function deleteInstitusi(formData: FormData): Promise<ApiResult> {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/institusi/${id}`);
  revalidatePath(PATH);
  return res;
}
