"use server";

import { apiPost, type ApiResult } from "@/lib/api";

export type AsistifMode = "perbaiki" | "parafrase" | "ringkas" | "panjangkan";

/**
 * Panggil AI asistif inline untuk menyunting satu field (non-otoritatif).
 * institusi_id sementara tetap 1 (auth ditunda).
 */
export async function assistText(input: {
  mode: AsistifMode;
  teks: string;
  konteks?: string;
}): Promise<ApiResult<{ teks: string }>> {
  return apiPost<{ teks: string }>("/ai/asistif", {
    institusi_id: 1,
    mode: input.mode,
    teks: input.teks,
    konteks: input.konteks ?? null,
  });
}
