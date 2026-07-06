"use server";

import { revalidatePath } from "next/cache";
import { apiPost } from "@/lib/api";

export async function tandaiDibaca(id: number) {
  const res = await apiPost(`/governance/notifikasi/${id}/dibaca`, {});
  revalidatePath("/governance/notifikasi");
  return res;
}
