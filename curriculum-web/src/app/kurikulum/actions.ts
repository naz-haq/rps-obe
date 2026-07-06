"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

export async function createKurikulum(formData: FormData) {
  const body = {
    institusi_id: DEFAULT_INSTITUSI,
    kode: (formData.get("kode") as string) || null,
    nama: formData.get("nama") as string,
    tahun: formData.get("tahun") as string,
    status: (formData.get("status") as string) || "draft",
  };
  const res = await apiPost("/kurikulum", body);
  revalidatePath("/kurikulum");
  return res;
}

export async function updateKurikulum(formData: FormData) {
  const id = formData.get("id") as string;
  const body = {
    kode: (formData.get("kode") as string) || null,
    nama: formData.get("nama") as string,
    tahun: formData.get("tahun") as string,
    status: formData.get("status") as string,
  };
  const res = await apiPut(`/kurikulum/${id}`, body);
  revalidatePath("/kurikulum");
  revalidatePath(`/kurikulum/${id}`);
  return res;
}

export async function deleteKurikulum(formData: FormData) {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/kurikulum/${id}`);
  revalidatePath("/kurikulum");
  return res;
}
