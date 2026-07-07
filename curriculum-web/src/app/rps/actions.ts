"use server";

import { revalidatePath } from "next/cache";
import { apiDelete, type ApiResult } from "@/lib/api";

export async function deleteRpsVersion(formData: FormData): Promise<ApiResult> {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/rps-versions/${id}`);
  revalidatePath("/rps");
  return res;
}
