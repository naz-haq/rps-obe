"use server";

import { revalidatePath } from "next/cache";
import { apiDelete, apiPost, apiPut, type ApiResult, type RoleData } from "@/lib/api";

export async function saveRolePermissions(
  roleId: number,
  permissions: string[],
): Promise<ApiResult<RoleData>> {
  const res = await apiPut<RoleData>(`/roles/${roleId}/permissions`, { permissions });
  if (res.ok) revalidatePath("/peran");
  return res;
}

export async function createRole(name: string): Promise<ApiResult<RoleData>> {
  const res = await apiPost<RoleData>("/roles", { name, permissions: [] });
  if (res.ok) revalidatePath("/peran");
  return res;
}

export async function deleteRole(roleId: number): Promise<ApiResult> {
  const res = await apiDelete(`/roles/${roleId}`);
  if (res.ok) revalidatePath("/peran");
  return res;
}
