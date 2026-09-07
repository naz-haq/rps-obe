"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, type ApiResult, type RincianPertemuan } from "@/lib/api";

type Aksi = "ajukan" | "setujui" | "revisi" | "tarik";

export async function aksiPersetujuan(input: {
  id: number;
  aksi: Aksi;
  catatan?: string;
  actor_nama?: string;
}): Promise<ApiResult> {
  const res = await apiPost(`/rps-versions/${input.id}/${input.aksi}`, {
    catatan: input.catatan ?? undefined,
    actor_nama: input.actor_nama ?? undefined,
  });
  revalidatePath(`/rps/${input.id}`);
  revalidatePath("/rps");
  revalidatePath("/persetujuan");
  return res;
}

/** Generate lanjutan: rincian per-pertemuan dari rencana mingguan (MK blok/profesi). */
export async function generateRincianPertemuan(id: number): Promise<ApiResult> {
  const res = await apiPost(`/rps-versions/${id}/generate-pertemuan`);
  revalidatePath(`/rps/${id}`);
  revalidatePath(`/rps/${id}/pertemuan`);
  return res;
}

/** Simpan/edit MANUAL rincian pertemuan satu pekan (alternatif jalur AI). */
export async function simpanRincianPertemuan(
  id: number,
  mingguKe: number,
  rincian: Partial<RincianPertemuan>[],
): Promise<ApiResult> {
  const res = await apiPut(`/rps-versions/${id}/minggu/${mingguKe}/rincian`, { rincian });
  revalidatePath(`/rps/${id}/pertemuan`);
  return res;
}
