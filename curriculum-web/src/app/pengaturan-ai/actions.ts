"use server";

import { revalidatePath } from "next/cache";
import { apiPut } from "@/lib/api";

export async function setProfil(formData: FormData): Promise<void> {
  const profil = String(formData.get("profil") ?? "");
  if (!profil) return;
  await apiPut("/ai/pengaturan", { profil });
  revalidatePath("/pengaturan-ai");
  revalidatePath("/dashboard");
}
