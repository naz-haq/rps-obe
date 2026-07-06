"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete } from "@/lib/api";

function base(kurikulumId: string) {
  return `/kurikulum/${kurikulumId}/cpl`;
}

export async function createCpl(formData: FormData) {
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    kurikulum_id: Number(kurikulumId),
    kode: formData.get("kode") as string,
    deskripsi: formData.get("deskripsi") as string,
    aspek: (formData.get("aspek") as string) || null,
    level_kkni: (formData.get("level_kkni") as string) || null,
    sumber: (formData.get("sumber") as string) || null,
  };
  const res = await apiPost("/cpl", body);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function updateCpl(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const body = {
    kode: formData.get("kode") as string,
    deskripsi: formData.get("deskripsi") as string,
    aspek: (formData.get("aspek") as string) || null,
    level_kkni: (formData.get("level_kkni") as string) || null,
    sumber: (formData.get("sumber") as string) || null,
  };
  const res = await apiPut(`/cpl/${id}`, body);
  revalidatePath(base(kurikulumId));
  return res;
}

export async function deleteCpl(formData: FormData) {
  const id = formData.get("id") as string;
  const kurikulumId = formData.get("kurikulum_id") as string;
  const res = await apiDelete(`/cpl/${id}`);
  revalidatePath(base(kurikulumId));
  return res;
}
