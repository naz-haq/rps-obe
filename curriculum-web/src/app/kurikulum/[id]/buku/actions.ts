"use server";

import { revalidatePath } from "next/cache";
import { apiPost, type BukuNaratif } from "@/lib/api";

/** Generate / regenerate narasi AI Buku Kurikulum lalu simpan. */
export async function generateNaratifBuku(
  kurikulumId: string,
): Promise<{ naratif: BukuNaratif; kosong: boolean; message: string; naratif_pada: string | null }> {
  const res = await apiPost<{
    naratif: BukuNaratif;
    kosong: boolean;
    message: string;
    naratif_pada: string | null;
  }>(`/kurikulum/${kurikulumId}/buku/naratif`);
  revalidatePath(`/kurikulum/${kurikulumId}/buku`);

  if (!res.ok || !res.data) {
    return {
      naratif: {},
      kosong: true,
      message: res.message ?? "Gagal membuat narasi. Coba lagi.",
      naratif_pada: null,
    };
  }
  return res.data;
}
