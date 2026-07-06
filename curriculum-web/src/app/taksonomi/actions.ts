"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete } from "@/lib/api";

const PATH = "/taksonomi";

function parseKataKerja(raw: string): string[] {
  return raw
    .split(/[,\n]/)
    .map((s) => s.trim())
    .filter((s) => s !== "");
}

function payload(formData: FormData) {
  return {
    institusi_id: 1,
    domain: formData.get("domain") as string,
    kerangka: formData.get("kerangka") as string,
    kode: formData.get("kode") as string,
    nama: formData.get("nama") as string,
    level: Number(formData.get("level")),
    deskripsi: (formData.get("deskripsi") as string) || null,
    kata_kerja: parseKataKerja((formData.get("kata_kerja") as string) ?? ""),
  };
}

export async function createTaksonomi(formData: FormData) {
  const res = await apiPost(PATH, payload(formData));
  revalidatePath("/taksonomi");
  return res;
}

export async function updateTaksonomi(formData: FormData) {
  const id = formData.get("id") as string;
  const res = await apiPut(`${PATH}/${id}`, payload(formData));
  revalidatePath("/taksonomi");
  return res;
}

export async function deleteTaksonomi(formData: FormData) {
  const id = formData.get("id") as string;
  const res = await apiDelete(`${PATH}/${id}`);
  revalidatePath("/taksonomi");
  return res;
}
