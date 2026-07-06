"use server";

import { revalidatePath } from "next/cache";
import { apiPost, type ApiResult } from "@/lib/api";

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
