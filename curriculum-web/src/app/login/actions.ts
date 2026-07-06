"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { API_BASE_URL } from "@/lib/api";
import { TOKEN_COOKIE } from "@/lib/auth";

export type LoginState = { error?: string; login?: string };

export async function loginAction(_prev: LoginState, formData: FormData): Promise<LoginState> {
  const login = String(formData.get("login") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  if (!login || !password) {
    return { error: "NIDN dan kata sandi wajib diisi.", login };
  }

  let res: Response;
  try {
    res = await fetch(`${API_BASE_URL}/auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      cache: "no-store",
      body: JSON.stringify({ login, password }),
    });
  } catch {
    return { error: "Tidak dapat terhubung ke server. Coba lagi.", login };
  }

  const json = (await res.json().catch(() => null)) as
    | { token?: string; message?: string; errors?: Record<string, string[]> }
    | null;

  if (!res.ok || !json?.token) {
    const pesan =
      json?.errors?.login?.[0] ?? json?.message ?? "NIDN atau kata sandi salah.";
    return { error: pesan, login };
  }

  const jar = await cookies();
  jar.set(TOKEN_COOKIE, json.token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: 60 * 60 * 24 * 7,
  });

  redirect("/dashboard");
}
