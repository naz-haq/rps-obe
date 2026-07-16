"use server";

import { revalidatePath } from "next/cache";
import { apiGet, apiPut, type AiLiveModels, type ApiResult } from "@/lib/api";

export async function setProfil(formData: FormData): Promise<void> {
  const profil = String(formData.get("profil") ?? "");
  if (!profil) return;
  await apiPut("/ai/pengaturan", { profil });
  revalidatePath("/pengaturan-ai");
  revalidatePath("/dashboard");
}

/** Simpan override model per-tugas (nilai kosong = ikut profil). */
export async function setModelOverride(
  override: Record<string, string>,
): Promise<ApiResult> {
  const res = await apiPut("/ai/pengaturan/model", { model_override: override });
  revalidatePath("/pengaturan-ai");
  revalidatePath("/dashboard");
  return res;
}

/** Ambil daftar model LIVE per-provider (ditarik dari API key aktif). */
export async function fetchLiveModels(): Promise<AiLiveModels> {
  try {
    const res = await apiGet<{ data: AiLiveModels }>("/ai/pengaturan/model-live");
    return res.data ?? {};
  } catch {
    return {};
  }
}
