import { notFound } from "next/navigation";
import { apiGet, type Single, type Paginated, type Kurikulum, type BahanKajian } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, EmptyState } from "@/components/ui";
import { KurikulumTabs } from "../tabs";
import { CreateBahanKajianButton, EditBahanKajianButton, DeleteBahanKajianButton } from "./forms";
import { ImportExcelButton } from "@/components/import-excel";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; q?: string }>;

export default async function BahanKajianPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: SearchParams;
}) {
  const { id } = await params;
  const sp = await searchParams;
  const sort = sp.sort ?? "nama";
  const dir = sp.dir ?? "asc";
  const page = sp.page ?? "1";

  let kurikulum: Kurikulum;
  try {
    const res = await apiGet<Single<Kurikulum>>(`/kurikulum/${id}`);
    kurikulum = res.data;
  } catch {
    notFound();
  }

  const list = await apiGet<Paginated<BahanKajian>>("/bahan-kajian", { kurikulum_id: id, sort, dir, page, q: sp.q, per_page: 15 }).catch(() => null);

  const params2 = { sort, dir, q: sp.q };
  const basePath = `/kurikulum/${id}/bahan-kajian`;

  return (
    <div>
      <PageHeader
        title={`Bahan Kajian — ${kurikulum.nama}`}
        subtitle="Kelola bahan kajian dan petakan ke CPL yang ditopangnya."
        actions={
          <>
            <ImportExcelButton
              jenis="bahan_kajian"
              kurikulumId={kurikulum.id}
              label="Bahan Kajian"
              fields={[
                { name: "nama", wajib: true },
                { name: "deskripsi" },
              ]}
              contoh={"nama,deskripsi\nFarmakologi,Kajian mekanisme kerja obat"}
            />
            <CreateBahanKajianButton kurikulumId={kurikulum.id} />
          </>
        }
      />
      <KurikulumTabs id={id} active="bahan-kajian" />

      {!list || list.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada Bahan Kajian" hint="Tambahkan bahan kajian atau impor lewat Onboarding." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Nama" column="nama" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <Th>Deskripsi</Th>
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((bk) => (
                <tr key={bk.id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{bk.nama}</Td>
                  <Td className="max-w-md text-muted">{bk.deskripsi ?? "—"}</Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditBahanKajianButton bk={bk} kurikulumId={kurikulum.id} />
                      <DeleteBahanKajianButton bk={bk} kurikulumId={kurikulum.id} />
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
