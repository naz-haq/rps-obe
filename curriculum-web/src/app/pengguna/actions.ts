"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, apiDelete, type ApiResult } from "@/lib/api";

const PATH = "/pengguna";

function buildBody(formData: FormData, withPassword: boolean) {
  const institusiId = (formData.get("institusi_id") as string) || "";
  const body: Record<string, unknown> = {
    name: (formData.get("name") as string) || "",
    email: (formData.get("email") as string) || "",
    nidn: (formData.get("nidn") as string) || null,
    jabatan: (formData.get("jabatan") as string) || null,
    institusi_id: institusiId ? Number(institusiId) : null,
    is_active: formData.get("is_active") === "on" || formData.get("is_active") === "1",
    roles: formData.getAll("roles").map((r) => String(r)),
  };
  const password = (formData.get("password") as string) || "";
  if (withPassword || password) body.password = password;
  return body;
}

export async function createUser(formData: FormData): Promise<ApiResult> {
  const res = await apiPost("/users", buildBody(formData, true));
  revalidatePath(PATH);
  return res;
}

export async function updateUser(formData: FormData): Promise<ApiResult> {
  const id = formData.get("id") as string;
  const res = await apiPut(`/users/${id}`, buildBody(formData, false));
  revalidatePath(PATH);
  return res;
}

export async function deleteUser(formData: FormData): Promise<ApiResult> {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/users/${id}`);
  revalidatePath(PATH);
  return res;
}
