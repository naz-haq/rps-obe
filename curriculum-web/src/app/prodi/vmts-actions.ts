"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, type ApiResult, type ProdiVmts } from "@/lib/api";

type VmtsPayload = {
  id?: number;
  institusi_id: number;
  label: string;
  visi: string | null;
  misi: string[];
  tujuan: string[];
  strategi: string[];
};

/** Buat/perbarui versi VMTS untuk sebuah prodi (dikelola di menu Prodi). */
export async function saveProdiVmts(payload: VmtsPayload): Promise<ApiResult<ProdiVmts>> {
  const res = payload.id
    ? await apiPut<ProdiVmts>(`/prodi-vmts/${payload.id}`, payload)
    : await apiPost<ProdiVmts>("/prodi-vmts", payload);
  revalidatePath("/prodi");
  return res;
}

export async function deleteProdiVmts(id: number): Promise<ApiResult> {
  const res = await apiDelete(`/prodi-vmts/${id}`);
  revalidatePath("/prodi");
  return res;
}
