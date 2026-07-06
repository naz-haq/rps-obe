"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, type ApiResult } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

export async function simpanCapaian(input: {
  id?: number;
  kode_mk: string;
  cpmk_id?: number | null;
  sub_cpmk_id?: number | null;
  angkatan?: string;
  jumlah_mahasiswa?: number | null;
  nilai_rata_rata?: number | null;
  persentase_capaian_minimal?: number | null;
}): Promise<ApiResult> {
  const body = {
    kode_mk: input.kode_mk,
    cpmk_id: input.cpmk_id ?? null,
    sub_cpmk_id: input.sub_cpmk_id ?? null,
    angkatan: input.angkatan || null,
    jumlah_mahasiswa: input.jumlah_mahasiswa ?? null,
    nilai_rata_rata: input.nilai_rata_rata ?? null,
    persentase_capaian_minimal: input.persentase_capaian_minimal ?? null,
  };

  const res = input.id
    ? await apiPut(`/capaian-mahasiswa/${input.id}`, body)
    : await apiPost("/capaian-mahasiswa", { institusi_id: DEFAULT_INSTITUSI, ...body });

  revalidatePath("/obaei/capaian");
  revalidatePath("/obaei");
  return res;
}

export async function hapusCapaian(id: number): Promise<ApiResult> {
  const res = await apiDelete(`/capaian-mahasiswa/${id}`);
  revalidatePath("/obaei/capaian");
  revalidatePath("/obaei");
  return res;
}
