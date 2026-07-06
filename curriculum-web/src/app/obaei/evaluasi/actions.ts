"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, type ApiResult } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

export async function buatEvaluasi(input: {
  cpl_id: number;
  periode?: string;
  ringkasan_naratif?: string;
}): Promise<ApiResult> {
  const res = await apiPost("/evaluasi-cpl", {
    institusi_id: DEFAULT_INSTITUSI,
    cpl_id: input.cpl_id,
    periode: input.periode || null,
    ringkasan_naratif: input.ringkasan_naratif || null,
  });
  revalidatePath("/obaei/evaluasi");
  return res;
}

export async function ubahEvaluasi(id: number, input: {
  periode?: string;
  ringkasan_naratif?: string;
}): Promise<ApiResult> {
  const res = await apiPut(`/evaluasi-cpl/${id}`, {
    periode: input.periode || null,
    ringkasan_naratif: input.ringkasan_naratif ?? null,
  });
  revalidatePath("/obaei/evaluasi");
  revalidatePath(`/obaei/evaluasi/${id}`);
  return res;
}

export async function hapusEvaluasi(id: number): Promise<ApiResult> {
  const res = await apiDelete(`/evaluasi-cpl/${id}`);
  revalidatePath("/obaei/evaluasi");
  return res;
}

export async function analisisEvaluasi(id: number, angkatan?: string): Promise<ApiResult> {
  const res = await apiPost(`/evaluasi-cpl/${id}/analisis`, { angkatan: angkatan || null });
  revalidatePath(`/obaei/evaluasi/${id}`);
  return res;
}

export async function finalisasiEvaluasi(id: number): Promise<ApiResult> {
  const res = await apiPost(`/evaluasi-cpl/${id}/finalisasi`, {});
  revalidatePath("/obaei/evaluasi");
  revalidatePath(`/obaei/evaluasi/${id}`);
  return res;
}

export async function tambahTindakLanjut(evaluasiId: number, input: {
  catatan: string;
  prioritas?: string;
}): Promise<ApiResult> {
  const res = await apiPost(`/evaluasi-cpl/${evaluasiId}/tindak-lanjut`, {
    catatan: input.catatan,
    prioritas: input.prioritas || null,
  });
  revalidatePath(`/obaei/evaluasi/${evaluasiId}`);
  return res;
}

export async function ubahTindakLanjut(id: number, evaluasiId: number, input: {
  catatan: string;
  prioritas?: string;
  status?: string;
}): Promise<ApiResult> {
  const res = await apiPut(`/tindak-lanjut/${id}`, {
    catatan: input.catatan,
    prioritas: input.prioritas || null,
    status: input.status || null,
  });
  revalidatePath(`/obaei/evaluasi/${evaluasiId}`);
  return res;
}

export async function hapusTindakLanjut(id: number, evaluasiId: number): Promise<ApiResult> {
  const res = await apiDelete(`/tindak-lanjut/${id}`);
  revalidatePath(`/obaei/evaluasi/${evaluasiId}`);
  return res;
}
