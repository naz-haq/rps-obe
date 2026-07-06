"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiDelete, type ApiResult } from "@/lib/api";

export async function saveAturan(input: {
  jenis_aturan: string;
  nilai: Record<string, unknown>;
}): Promise<ApiResult> {
  const res = await apiPost("/konfigurasi-aturan/upsert", {
    institusi_id: 1,
    jenis_aturan: input.jenis_aturan,
    nilai: input.nilai,
  });
  revalidatePath("/konfigurasi-aturan");
  return res;
}

export async function deleteAturan(id: number): Promise<ApiResult> {
  const res = await apiDelete(`/konfigurasi-aturan/${id}`);
  revalidatePath("/konfigurasi-aturan");
  return res;
}
