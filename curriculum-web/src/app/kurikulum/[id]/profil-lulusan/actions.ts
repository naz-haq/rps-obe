"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete } from "@/lib/api";

function base(kurikulumId: string) {
  return `/kurikulum/${kurikulumId}/profil-lulusan`;
}

export async function createProfil(formData: FormData) {
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    kurikulum_id: Number(kurikulumId),
    kode: formData.get("kode") as string,
    deskripsi: formData.get("deskripsi") as string,
  };
  const res = await apiPost("/profil-lulusan", body);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function updateProfil(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    kode: formData.get("kode") as string,
    deskripsi: formData.get("deskripsi") as string,
  };
  const res = await apiPut(`/profil-lulusan/${id}`, body);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function deleteProfil(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const res = await apiDelete(`/profil-lulusan/${id}`);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function toggleProfilLulusanLink(
  kurikulumId: number,
  profilLulusanId: number,
  cplId: number,
  active: boolean,
) {
  const path = `/kurikulum/${kurikulumId}/matriks-profil-lulusan/link`;
  const body = { profil_lulusan_id: profilLulusanId, cpl_id: cplId };
  const res = active ? await apiPost(path, body) : await apiDelete(path, body);
  revalidatePath(`/kurikulum/${kurikulumId}/profil-lulusan`);
  return res;
}

export async function suggestProfilLulusanLinks(kurikulumId: number) {
  return apiPost<{ links: { profil_lulusan_id: number; cpl_id: number }[] }>(
    `/kurikulum/${kurikulumId}/matriks-profil-lulusan/suggest`,
    {},
  );
}
