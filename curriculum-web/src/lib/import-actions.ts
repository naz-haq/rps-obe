"use server";

import { revalidatePath } from "next/cache";
import { apiPost } from "@/lib/api";

export type ImportJenis = "cpl" | "mata_kuliah" | "bahan_kajian" | "profil_lulusan";

export type ImportRingkasan = {
  ok: boolean;
  message?: string;
  dibuat?: number;
  diperbarui?: number;
  dilewati?: number;
  galat?: string[];
};

/**
 * Impor massal baris Excel/CSV ke entitas kurikulum via endpoint onboarding.
 * `rows` = array 2D (baris pertama = header). Pemetaan kolom otomatis di server.
 * `institusiId` = institusi pemilik kurikulum (dipakai server untuk verifikasi
 * kepemilikan tenant); harus sama dengan institusi_id kurikulum terkait.
 */
export async function importExcelRows(
  jenis: ImportJenis,
  kurikulumId: number,
  institusiId: number,
  rows: unknown[][],
  revalidate?: string,
): Promise<ImportRingkasan> {
  const res = await apiPost<ImportRingkasan>("/onboarding/import", {
    institusi_id: institusiId,
    kurikulum_id: kurikulumId,
    jenis,
    rows,
  });

  if (revalidate) revalidatePath(revalidate);

  const data = (res.data ?? {}) as ImportRingkasan;
  return {
    ok: res.ok,
    message: res.ok ? undefined : res.message ?? "Impor gagal.",
    dibuat: data.dibuat ?? 0,
    diperbarui: data.diperbarui ?? 0,
    dilewati: data.dilewati ?? 0,
    galat: data.galat ?? [],
  };
}
