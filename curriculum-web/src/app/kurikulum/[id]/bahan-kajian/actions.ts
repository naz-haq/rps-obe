"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete } from "@/lib/api";

function base(kurikulumId: string) {
  return `/kurikulum/${kurikulumId}/bahan-kajian`;
}

export async function createBahanKajian(formData: FormData) {
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    kurikulum_id: Number(kurikulumId),
    nama: formData.get("nama") as string,
    deskripsi: (formData.get("deskripsi") as string) || null,
  };
  const res = await apiPost("/bahan-kajian", body);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function updateBahanKajian(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    nama: formData.get("nama") as string,
    deskripsi: (formData.get("deskripsi") as string) || null,
  };
  const res = await apiPut(`/bahan-kajian/${id}`, body);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function deleteBahanKajian(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const res = await apiDelete(`/bahan-kajian/${id}`);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function toggleBahanKajianLink(
  kurikulumId: number,
  cplId: number,
  bahanKajianId: number,
  active: boolean,
) {
  const path = `/kurikulum/${kurikulumId}/matriks-bahan-kajian/link`;
  const body = { cpl_id: cplId, bahan_kajian_id: bahanKajianId };
  const res = active ? await apiPost(path, body) : await apiDelete(path, body);
  revalidatePath(`/kurikulum/${kurikulumId}/bahan-kajian`);
  return res;
}

export async function suggestBahanKajianLinks(kurikulumId: number) {
  return apiPost<{ links: { cpl_id: number; bahan_kajian_id: number }[] }>(
    `/kurikulum/${kurikulumId}/matriks-bahan-kajian/suggest`,
    {},
  );
}
