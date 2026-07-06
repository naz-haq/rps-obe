"use server";

import { revalidatePath } from "next/cache";
import { apiPost, type ApiResult } from "@/lib/api";

export async function tinjau(input: {
  id: number;
  aksi: "setujui" | "revisi";
  catatan?: string;
  actor_nama?: string;
}): Promise<ApiResult> {
  const res = await apiPost(`/rps-versions/${input.id}/${input.aksi}`, {
    catatan: input.catatan ?? undefined,
    actor_nama: input.actor_nama ?? undefined,
  });
  revalidatePath("/persetujuan");
  revalidatePath(`/rps/${input.id}`);
  revalidatePath("/rps");
  return res;
}
