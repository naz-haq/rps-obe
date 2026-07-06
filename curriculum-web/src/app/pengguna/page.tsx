import { apiGet, type Paginated, type UserAccount, type RoleData, type InstitusiData } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { CreateUserButton, EditUserButton, DeleteUserButton } from "./forms";

type SearchParams = Promise<{
  sort?: string;
  dir?: string;
  page?: string;
  q?: string;
  role?: string;
  is_active?: string;
}>;

const ROLE_LABELS: Record<string, string> = {
  "super-admin": "Super Admin",
  "admin-akademik": "Admin Akademik",
  "pimpinan-fakultas": "Pimpinan Fakultas",
  kaprodi: "Kaprodi/Sekprodi",
  "koordinator-mk": "Koordinator MK",
  dosen: "Dosen",
  stpmp: "STPMP",
  psmf: "PSMF",
  lpm: "LPM",
};

export default async function PenggunaPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "name";
  const dir = sp.dir ?? "asc";
  const page = sp.page ?? "1";

  const [usersRes, rolesRes, institusiRes] = await Promise.all([
    apiGet<Paginated<UserAccount>>("/users", {
      sort,
      dir,
      page,
      q: sp.q,
      role: sp.role,
      is_active: sp.is_active,
      per_page: 15,
    }).catch(() => null),
    apiGet<{ data: RoleData[] }>("/roles").catch(() => ({ data: [] as RoleData[] })),
    apiGet<{ data: InstitusiData[] }>("/institusi").catch(() => ({ data: [] as InstitusiData[] })),
  ]);

  const roles = rolesRes.data;
  const institusi = institusiRes.data;
  const params = { sort, dir, q: sp.q, role: sp.role, is_active: sp.is_active };
  const basePath = "/pengguna";

  return (
    <div>
      <PageHeader
        title="Pengguna"
        subtitle="Kelola akun pengguna, tetapkan peran, dan atur unit/institusi. Peran menentukan hak akses & menu yang tampil."
        actions={<CreateUserButton roles={roles} institusi={institusi} />}
      />

      <Card className="mb-4">
        <form className="flex flex-wrap items-end gap-3 px-4 py-3" method="get">
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-ink">Cari</span>
            <input
              name="q"
              defaultValue={sp.q ?? ""}
              placeholder="Nama, email, atau NIDN"
              className="w-56 rounded-lg border border-border bg-surface px-3 py-2 text-sm outline-none focus-ring"
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-ink">Peran</span>
            <select
              name="role"
              defaultValue={sp.role ?? ""}
              className="rounded-lg border border-border bg-surface px-3 py-2 text-sm outline-none focus-ring"
            >
              <option value="">Semua peran</option>
              {roles.map((r) => (
                <option key={r.id} value={r.name}>{r.label}</option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-ink">Status</span>
            <select
              name="is_active"
              defaultValue={sp.is_active ?? ""}
              className="rounded-lg border border-border bg-surface px-3 py-2 text-sm outline-none focus-ring"
            >
              <option value="">Semua</option>
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </label>
          <button type="submit" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Terapkan
          </button>
        </form>
      </Card>

      {!usersRes || usersRes.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada pengguna" hint="Tambahkan pengguna lewat tombol di atas." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Nama" column="name" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="NIDN" column="nidn" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th>Email</Th>
                <Th>Peran</Th>
                <Th>Unit</Th>
                <SortableTh label="Status" column="is_active" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {usersRes.data.map((u) => (
                <tr key={u.id} className="hover:bg-gray-50">
                  <Td>
                    <p className="font-medium text-ink">{u.name}</p>
                    {u.jabatan && <p className="text-xs text-muted">{u.jabatan}</p>}
                  </Td>
                  <Td className="font-medium text-ink">{u.nidn ?? "—"}</Td>
                  <Td className="text-muted">{u.email ?? "—"}</Td>
                  <Td>
                    <div className="flex flex-wrap gap-1">
                      {u.roles.length === 0 ? (
                        <span className="text-xs text-gray-400">—</span>
                      ) : (
                        u.roles.map((r) => (
                          <Badge key={r} tone="neutral">{ROLE_LABELS[r] ?? r}</Badge>
                        ))
                      )}
                    </div>
                  </Td>
                  <Td className="text-muted">{u.institusi?.nama ?? "—"}</Td>
                  <Td>
                    <Badge tone={u.is_active ? "ok" : "danger"}>{u.is_active ? "Aktif" : "Nonaktif"}</Badge>
                  </Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditUserButton user={u} roles={roles} institusi={institusi} />
                      <DeleteUserButton user={u} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={usersRes.meta} basePath={basePath} params={params} />
        </Card>
      )}
    </div>
  );
}
