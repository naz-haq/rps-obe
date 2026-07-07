import { notFound } from "next/navigation";
import { apiGet, type Single, type Paginated, type Kurikulum, type ProfilLulusan } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, EmptyState } from "@/components/ui";
import { KurikulumTabs } from "../tabs";
import { CreateProfilButton, EditProfilButton, DeleteProfilButton } from "./forms";
import { ImportExcelButton } from "@/components/import-excel";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; q?: string }>;

export default async function ProfilLulusanPage({
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

  let list: Paginated<ProfilLulusan> | null = null;
  try {
    list = await apiGet<Paginated<ProfilLulusan>>("/profil-lulusan", {
      kurikulum_id: id,
      sort,
      dir,
      page,
      q: sp.q,
      per_page: 15,
    });
  } catch {
    list = null;
  }

  const params2 = { sort, dir, q: sp.q };
  const basePath = `/kurikulum/${id}/profil-lulusan`;

  return (
    <div>
      <PageHeader
        title={`Profil Lulusan — ${kurikulum.nama}`}
        subtitle="Kelola Profil Lulusan (PL) pada kurikulum ini."
        actions={
          <>
            <ImportExcelButton
              jenis="profil_lulusan"
              kurikulumId={kurikulum.id}
              institusiId={kurikulum.institusi_id}
              label="Profil Lulusan"
              fields={[
                { name: "kode", wajib: true },
                { name: "deskripsi", wajib: true },
              ]}
              contoh={"kode,deskripsi\nPL-01,Apoteker yang mampu mengelola pelayanan kefarmasian"}
            />
            <CreateProfilButton kurikulumId={kurikulum.id} />
          </>
        }
      />
      <KurikulumTabs id={id} active="profil-lulusan" />

      {!list || list.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada Profil Lulusan" hint="Tambahkan profil lulusan untuk kurikulum ini." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Kode" column="kode" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <Th>Deskripsi</Th>
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((p) => (
                <tr key={p.id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{p.kode}</Td>
                  <Td className="max-w-xl text-muted">{p.deskripsi}</Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditProfilButton p={p} kurikulumId={kurikulum.id} />
                      <DeleteProfilButton p={p} kurikulumId={kurikulum.id} />
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
