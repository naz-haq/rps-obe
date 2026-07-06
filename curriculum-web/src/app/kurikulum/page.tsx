import Link from "next/link";
import { apiGet, type Paginated, type Kurikulum } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { CreateKurikulumButton, EditKurikulumButton, DeleteKurikulumButton } from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string; q?: string }>;

const STATUS_TONE: Record<string, "ok" | "neutral" | "warn"> = {
  berlaku: "ok",
  arsip: "neutral",
  draft: "warn",
};

export default async function KurikulumPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "tahun";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  let list: Paginated<Kurikulum> | null = null;
  let error: string | null = null;
  try {
    list = await apiGet<Paginated<Kurikulum>>("/kurikulum", {
      sort,
      dir,
      page,
      status: sp.status,
      q: sp.q,
      per_page: 15,
    });
  } catch {
    error = "Tidak dapat memuat daftar kurikulum. Pastikan backend berjalan di :8100.";
  }

  const params = { sort, dir, status: sp.status, q: sp.q };

  return (
    <div>
      <PageHeader
        title="Peta Kurikulum"
        subtitle="Kelola kurikulum, capaian pembelajaran, dan pemetaan CPL × mata kuliah."
        actions={<CreateKurikulumButton />}
      />

      {error ? (
        <Card>
          <div className="p-5 text-sm text-red-600">{error}</div>
        </Card>
      ) : !list || list.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada kurikulum" hint="Tambahkan kurikulum atau impor lewat Onboarding." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Nama" column="nama" sort={sort} dir={dir} basePath="/kurikulum" params={params} />
                <Th>Kode</Th>
                <SortableTh label="Tahun" column="tahun" sort={sort} dir={dir} basePath="/kurikulum" params={params} />
                <Th className="text-right">MK</Th>
                <Th className="text-right">CPL</Th>
                <SortableTh label="Status" column="status" sort={sort} dir={dir} basePath="/kurikulum" params={params} />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((k) => (
                <tr key={k.id} className="hover:bg-gray-50">
                  <Td>
                    <Link href={`/kurikulum/${k.id}`} className="font-medium text-brand-700 hover:underline">
                      {k.nama}
                    </Link>
                  </Td>
                  <Td className="text-muted">{k.kode ?? "—"}</Td>
                  <Td>{k.tahun}</Td>
                  <Td className="text-right tabular-nums">{k.mata_kuliah_count ?? 0}</Td>
                  <Td className="text-right tabular-nums">{k.cpl_count ?? 0}</Td>
                  <Td>
                    <Badge tone={STATUS_TONE[k.status] ?? "neutral"}>{k.status}</Badge>
                  </Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditKurikulumButton k={k} />
                      <DeleteKurikulumButton k={k} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={list.meta} basePath="/kurikulum" params={params} />
        </Card>
      )}
    </div>
  );
}
