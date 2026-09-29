"use server";

import { revalidatePath } from "next/cache";
import { apiDelete, apiPostForm, type ApiResult } from "@/lib/api";

export async function unggahLogo(id: number, formData: FormData): Promise<ApiResult> {
  const fd = new FormData();
  fd.append("file", formData.get("file") as File);
  const res = await apiPostForm(`/institusi/${id}/logo`, fd);
  revalidatePath("/pengaturan-dokumen");
  return res;
}

export async function hapusLogo(id: number): Promise<ApiResult> {
  const res = await apiDelete(`/institusi/${id}/logo`);
  revalidatePath("/pengaturan-dokumen");
  return res;
}
