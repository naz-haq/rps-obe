import { notFound } from "next/navigation";
import { apiGet, type Single, type Paginated, type Kurikulum, type Cpl } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { KurikulumTabs } from "../tabs";
import { CreateCplButton, EditCplButton, DeleteCplButton } from "./forms";
import { ImportExcelButton } from "@/components/import-excel";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; aspek?: string; q?: string }>;

const ASPEK_LABEL: Record<string, string> = {
  sikap: "Sikap",
  pengetahuan: "Pengetahuan",
  keterampilan_umum: "Ket. Umum",
  keterampilan_khusus: "Ket. Khusus",
};

export default async function CplPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: SearchParams;
}) {
  const { id } = await params;
  const sp = await searchParams;
  const sort = sp.sort ?? "kode";
  const dir = sp.dir ?? "asc";
  const page = sp.page ?? "1";

  let kurikulum: Kurikulum;
  try {
    const res = await apiGet<Single<Kurikulum>>(`/kurikulum/${id}`);
    kurikulum = res.data;
  } catch {
    notFound();
  }

  let list: Paginated<Cpl> | null = null;
  try {
    list = await apiGet<Paginated<Cpl>>("/cpl", {
      kurikulum_id: id,
      sort,
      dir,
      page,
      aspek: sp.aspek,
      q: sp.q,
      per_page: 15,
    });
  } catch {
    list = null;
  }

  const params2 = { sort, dir, aspek: sp.aspek, q: sp.q };
  const basePath = `/kurikulum/${id}/cpl`;

  return (
    <div>
      <PageHeader
        title={`CPL — ${kurikulum.nama}`}
        subtitle="Kelola Capaian Pembelajaran Lulusan pada kurikulum ini."
        actions={
          <>
            <ImportExcelButton
              jenis="cpl"
              kurikulumId={kurikulum.id}
              label="CPL"
              fields={[
                { name: "kode", wajib: true },
                { name: "deskripsi", wajib: true },
                { name: "aspek" },
                { name: "level_kkni" },
                { name: "sumber" },
              ]}
              contoh={"kode,deskripsi,aspek\nCPL-01,Mampu menerapkan prinsip...,keterampilan_khusus"}
            />
            <CreateCplButton kurikulumId={kurikulum.id} />
          </>
        }
      />
      <KurikulumTabs id={id} active="cpl" />

      {!list || list.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada CPL" hint="Tambahkan CPL atau impor lewat Onboarding." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Kode" column="kode" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <Th>Deskripsi</Th>
                <SortableTh label="Aspek" column="aspek" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <SortableTh label="KKNI" column="level_kkni" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((c) => (
                <tr key={c.id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{c.kode}</Td>
                  <Td className="max-w-md text-muted">{c.deskripsi}</Td>
                  <Td>{c.aspek ? <Badge tone="neutral">{ASPEK_LABEL[c.aspek] ?? c.aspek}</Badge> : <span className="text-muted">—</span>}</Td>
                  <Td>{c.level_kkni ?? "—"}</Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditCplButton c={c} kurikulumId={kurikulum.id} />
                      <DeleteCplButton c={c} kurikulumId={kurikulum.id} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={list.meta} basePath={basePath} params={params2} />
        </Card>
      )}
    </div>
  );
}
