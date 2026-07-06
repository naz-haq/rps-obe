"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, type ApiResult, type PindaiOverlapRingkasan } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

/** Jalankan deteksi overlap deterministik. */
export async function pindaiOverlap(kurikulumId?: number): Promise<ApiResult<{ data: PindaiOverlapRingkasan }>> {
  const res = await apiPost<{ data: PindaiOverlapRingkasan }>("/validasi-overlap/pindai", {
    institusi_id: DEFAULT_INSTITUSI,
    kurikulum_id: kurikulumId ?? null,
  });
  revalidatePath("/validasi-overlap");
  return res;
}

/** Lengkapi analisis + rekomendasi satu temuan dengan AI. */
export async function analisisOverlap(id: number): Promise<ApiResult> {
  const res = await apiPost(`/validasi-overlap/${id}/analisis`, {});
  revalidatePath("/validasi-overlap");
  return res;
}

/** Tinjauan manusia: tetapkan status akhir + catatan rekomendasi. */
export async function reviewOverlap(input: {
  id: number;
  status: string;
  rekomendasi?: string;
}): Promise<ApiResult> {
  const res = await apiPut(`/validasi-overlap/${input.id}/review`, {
    status: input.status,
    rekomendasi: input.rekomendasi ?? null,
  });
  revalidatePath("/validasi-overlap");
  return res;
}
