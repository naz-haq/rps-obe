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

/** Buat versi VMTS baru (auto-tautkan ke kurikulum) atau perbarui yang ada. */
export async function saveVmts(kurikulumId: string, payload: VmtsPayload): Promise<ApiResult<ProdiVmts>> {
  let res: ApiResult<ProdiVmts>;
  if (payload.id) {
    res = await apiPut<ProdiVmts>(`/prodi-vmts/${payload.id}`, payload);
  } else {
    res = await apiPost<ProdiVmts>("/prodi-vmts", payload);
    if (res.ok && res.data?.id) {
      await apiPut(`/kurikulum/${kurikulumId}`, { vmts_id: res.data.id });
    }
  }
  revalidatePath(`/kurikulum/${kurikulumId}/buku`);
  return res;
}

/** Pilih versi VMTS mana yang dipakai kurikulum ini (atau lepas). */
export async function selectVmts(kurikulumId: string, vmtsId: number | null): Promise<ApiResult> {
  const res = await apiPut(`/kurikulum/${kurikulumId}`, { vmts_id: vmtsId });
  revalidatePath(`/kurikulum/${kurikulumId}/buku`);
  return res;
}

export async function removeVmts(kurikulumId: string, vmtsId: number): Promise<ApiResult> {
  const res = await apiDelete(`/prodi-vmts/${vmtsId}`);
  revalidatePath(`/kurikulum/${kurikulumId}/buku`);
  return res;
}
