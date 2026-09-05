"use server";

import { apiPost, type ApiResult } from "@/lib/api";

export type AsistifMode = "perbaiki" | "parafrase" | "ringkas" | "panjangkan" | "generate";

/**
 * Panggil AI asistif inline untuk satu field (non-otoritatif).
 * Mode "generate" membuat isi baru dari `data` (fakta konteks), mode lain
 * menyunting `teks` yang sudah ada. Tenant ditentukan server dari user login.
 */
export async function assistText(input: {
  mode: AsistifMode;
  teks: string;
  konteks?: string;
  data?: string;
}): Promise<ApiResult<{ teks: string }>> {
  return apiPost<{ teks: string }>("/ai/asistif", {
    mode: input.mode,
    teks: input.teks,
    konteks: input.konteks ?? null,
    data: input.data ?? null,
  });
}
