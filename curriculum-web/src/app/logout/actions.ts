"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { API_BASE_URL } from "@/lib/api";
import { TOKEN_COOKIE } from "@/lib/auth";

export async function logoutAction(): Promise<void> {
  const jar = await cookies();
  const token = jar.get(TOKEN_COOKIE)?.value;

  if (token) {
    try {
      await fetch(`${API_BASE_URL}/auth/logout`, {
        method: "POST",
        headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
        cache: "no-store",
      });
    } catch {
      /* abaikan; tetap hapus cookie lokal */
    }
  }

  jar.delete(TOKEN_COOKIE);
  redirect("/login");
}
