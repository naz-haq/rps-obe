"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, type ApiResult } from "@/lib/api";

/** Buat override prompt untuk sebuah slot (jenis_output). */
export async function createOverride(fd: FormData): Promise<ApiResult> {
  const payload = {
    jenis_output: String(fd.get("jenis_output") ?? ""),
    sistem_prompt: String(fd.get("sistem_prompt") ?? ""),
    skema_output: String(fd.get("skema_output") ?? "").trim() || null,
    jenis_mk: (String(fd.get("jenis_mk") ?? "") || null) as string | null,
    aktif: true,
  };
  const res = await apiPost("/prompt-templates", payload);
  if (res.ok) revalidatePath("/prompts");
  return res;
}

/** Ubah override yang sudah ada. */
export async function updateOverride(fd: FormData): Promise<ApiResult> {
  const id = String(fd.get("id") ?? "");
  if (!id) return { ok: false, status: 0, message: "ID kosong" };
  const payload = {
    sistem_prompt: String(fd.get("sistem_prompt") ?? ""),
    skema_output: String(fd.get("skema_output") ?? "").trim() || null,
    jenis_mk: (String(fd.get("jenis_mk") ?? "") || null) as string | null,
    aktif: fd.get("aktif") === "on" || fd.get("aktif") === "true",
  };
  const res = await apiPut(`/prompt-templates/${id}`, payload);
  if (res.ok) revalidatePath("/prompts");
  return res;
}

/** Hapus override -> slot kembali memakai default config. */
export async function deleteOverride(fd: FormData): Promise<ApiResult> {
  const id = String(fd.get("id") ?? "");
  if (!id) return { ok: false, status: 0, message: "ID kosong" };
  const res = await apiDelete(`/prompt-templates/${id}`);
  if (res.ok) revalidatePath("/prompts");
  return res;
}
