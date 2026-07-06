import { PageHeader } from "@/components/ui";
import { apiGet, type RbacKatalog, type RoleData, type Single } from "@/lib/api";
import { RoleMatrix } from "./matrix";

export const dynamic = "force-dynamic";

export default async function PeranPage() {
  const [rolesRes, katalogRes] = await Promise.all([
    apiGet<{ data: RoleData[] }>("/roles").catch(() => ({ data: [] as RoleData[] })),
    apiGet<Single<RbacKatalog>>("/rbac/katalog").catch(() => ({ data: { groups: [] } })),
  ]);

  return (
    <div>
      <PageHeader
        title="Peran & Hak Akses"
        subtitle="Matriks ceklist: atur izin setiap peran. Centang menentukan aksi yang boleh dilakukan sekaligus menu yang tampil bagi peran tersebut."
      />
      <RoleMatrix key={rolesRes.data.map((r) => r.id).join("-")} roles={rolesRes.data} groups={katalogRes.data.groups} />
    </div>
  );
}
