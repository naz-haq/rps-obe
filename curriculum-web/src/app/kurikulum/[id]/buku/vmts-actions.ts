"use server";

import { revalidatePath } from "next/cache";
import { apiPut, type ApiResult } from "@/lib/api";

/** Pilih versi VMTS mana yang dipakai kurikulum ini (atau lepas). */
export async function selectVmts(kurikulumId: string, vmtsId: number | null): Promise<ApiResult> {
  const res = await apiPut(`/kurikulum/${kurikulumId}`, { vmts_id: vmtsId });
  revalidatePath(`/kurikulum/${kurikulumId}/buku`);
  return res;
}
