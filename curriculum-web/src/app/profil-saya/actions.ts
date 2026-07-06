"use server";

import { apiPut, type ApiResult } from "@/lib/api";

/** Update nama & email milik sendiri. */
export async function updateProfil(formData: FormData): Promise<ApiResult> {
  const email = String(formData.get("email") ?? "").trim();
  return apiPut("/auth/profile", {
    name: String(formData.get("name") ?? "").trim(),
    email: email || null,
  });
}

/** Ubah kata sandi sendiri (verifikasi kata sandi saat ini di server). */
export async function updatePassword(formData: FormData): Promise<ApiResult> {
  return apiPut("/auth/password", {
    current_password: String(formData.get("current_password") ?? ""),
    password: String(formData.get("password") ?? ""),
    password_confirmation: String(formData.get("password_confirmation") ?? ""),
  });
}
