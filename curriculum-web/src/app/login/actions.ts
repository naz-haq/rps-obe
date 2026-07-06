"use server";

import { cookies, headers } from "next/headers";
import { redirect } from "next/navigation";
import { API_BASE_URL } from "@/lib/api";
import { TOKEN_COOKIE } from "@/lib/auth";

export type LoginState = { error?: string; login?: string };

/**
 * Verifikasi token Cloudflare Turnstile di sisi server.
 * Hanya berjalan bila TURNSTILE_SECRET_KEY diisi; jika kosong, verifikasi dilewati.
 */
async function verifyTurnstile(token: string, remoteIp?: string): Promise<boolean> {
  const secret = process.env.TURNSTILE_SECRET_KEY;
  if (!secret) return true; // Turnstile nonaktif → lewati.
  if (!token) return false;

  const body = new URLSearchParams({ secret, response: token });
  if (remoteIp) body.set("remoteip", remoteIp);

  try {
    const res = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      cache: "no-store",
      body,
    });
    const data = (await res.json().catch(() => null)) as { success?: boolean } | null;
    return Boolean(data?.success);
  } catch {
    return false;
  }
}

export async function loginAction(_prev: LoginState, formData: FormData): Promise<LoginState> {
  const login = String(formData.get("login") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  if (!login || !password) {
    return { error: "NIDN dan kata sandi wajib diisi.", login };
  }

  // Verifikasi Turnstile sebelum meneruskan kredensial ke backend.
  if (process.env.TURNSTILE_SECRET_KEY) {
    const turnstileToken = String(formData.get("cf-turnstile-response") ?? "");
    const hdrs = await headers();
    const remoteIp =
      hdrs.get("cf-connecting-ip") ?? hdrs.get("x-forwarded-for")?.split(",")[0]?.trim();
    const ok = await verifyTurnstile(turnstileToken, remoteIp || undefined);
    if (!ok) {
      return { error: "Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.", login };
    }
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
