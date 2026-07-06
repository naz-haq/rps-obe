"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, type ApiResult } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

export async function simpanTarget(input: {
  id?: number;
  cpl_id: number;
  angkatan?: string;
  ambang_nilai?: number | null;
  persentase_target?: number | null;
}): Promise<ApiResult> {
  const body = {
    cpl_id: input.cpl_id,
    angkatan: input.angkatan || null,
    ambang_nilai: input.ambang_nilai ?? null,
    persentase_target: input.persentase_target ?? null,
  };

  const res = input.id
    ? await apiPut(`/target-cpl/${input.id}`, body)
    : await apiPost("/target-cpl", { institusi_id: DEFAULT_INSTITUSI, ...body });

  revalidatePath("/obaei/target");
  revalidatePath("/obaei");
  return res;
}

export async function hapusTarget(id: number): Promise<ApiResult> {
  const res = await apiDelete(`/target-cpl/${id}`);
  revalidatePath("/obaei/target");
  revalidatePath("/obaei");
  return res;
}
