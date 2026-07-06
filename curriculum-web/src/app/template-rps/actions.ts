"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, apiPostForm, type ApiResult } from "@/lib/api";

/** Unggah berkas template baru (multipart). */
export async function uploadTemplate(fd: FormData): Promise<ApiResult> {
  const berkas = fd.get("berkas");
  if (!(berkas instanceof File) || berkas.size === 0) {
    return { ok: false, status: 0, message: "Pilih berkas template terlebih dahulu." };
  }

  const form = new FormData();
  form.append("institusi_id", "1");
  form.append("nama", String(fd.get("nama") ?? "").trim());
  form.append("keterangan", String(fd.get("keterangan") ?? "").trim());
  form.append("is_active", fd.get("is_active") === "on" ? "1" : "0");
  form.append("berkas", berkas);

  const res = await apiPostForm("/template-rps", form);
  if (res.ok) revalidatePath("/template-rps");
  return res;
}

/** Tandai template sebagai aktif. */
export async function activateTemplate(fd: FormData): Promise<ApiResult> {
  const id = String(fd.get("id") ?? "");
  if (!id) return { ok: false, status: 0, message: "ID kosong" };
  const res = await apiPost(`/template-rps/${id}/activate`, {});
  if (res.ok) revalidatePath("/template-rps");
  return res;
}

/** Ubah metadata (nama/keterangan) template. */
export async function updateTemplate(fd: FormData): Promise<ApiResult> {
  const id = String(fd.get("id") ?? "");
  if (!id) return { ok: false, status: 0, message: "ID kosong" };
  const res = await apiPut(`/template-rps/${id}`, {
    nama: String(fd.get("nama") ?? "").trim(),
    keterangan: String(fd.get("keterangan") ?? "").trim() || null,
  });
  if (res.ok) revalidatePath("/template-rps");
  return res;
}

/** Hapus template + berkasnya. */
export async function deleteTemplate(fd: FormData): Promise<ApiResult> {
  const id = String(fd.get("id") ?? "");
  if (!id) return { ok: false, status: 0, message: "ID kosong" };
  const res = await apiDelete(`/template-rps/${id}`);
  if (res.ok) revalidatePath("/template-rps");
  return res;
}
