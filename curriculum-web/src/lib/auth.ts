/**
 * Helper Auth server-side (Sanctum token disimpan di cookie httpOnly).
 * Dipakai oleh layout, middleware tak dapat memakai ini (Edge) — middleware
 * hanya memeriksa keberadaan cookie.
 */
import { cookies } from "next/headers";
import { API_BASE_URL } from "@/lib/api";

export const TOKEN_COOKIE = "rps_token";

export type AuthInstitusi = { id: number; nama: string; jenis: string };

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  nidn: string | null;
  jabatan: string | null;
  is_active: boolean;
  institusi_id: number | null;
  institusi: AuthInstitusi | null;
  roles: string[];
  permissions: string[];
};

export async function getToken(): Promise<string | null> {
  const jar = await cookies();
  return jar.get(TOKEN_COOKIE)?.value ?? null;
}

/** Ambil profil pengguna aktif; null bila belum login / token tak valid. */
export async function getCurrentUser(): Promise<AuthUser | null> {
  const token = await getToken();
  if (!token) return null;

  try {
    const res = await fetch(`${API_BASE_URL}/auth/me`, {
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
    });
    if (!res.ok) return null;
    const json = (await res.json()) as { data: AuthUser };
    return json.data;
  } catch {
    return null;
  }
}

export function can(user: AuthUser | null, permission: string): boolean {
  if (!user) return false;
  return user.permissions.includes(permission);
}
