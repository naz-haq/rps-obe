import { notFound } from "next/navigation";
import { apiGet, type Single, type Paginated, type Kurikulum, type MataKuliah, type InstitusiData } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { KurikulumTabs } from "../tabs";
import { CreateMkButton, EditMkButton, DeleteMkButton } from "./forms";
import { ImportExcelButton } from "@/components/import-excel";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; jenis_mk?: string; semester?: string; q?: string }>;

const JENIS_LABEL: Record<string, string> = { murni: "Teori", praktikum: "Praktikum" };

export default async function MataKuliahPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: SearchParams;
}) {
  const { id } = await params;
  const sp = await searchParams;
  const sort = sp.sort ?? "kode_mk";
  const dir = sp.dir ?? "asc";
  const page = sp.page ?? "1";

  let kurikulum: Kurikulum;
  try {
    const res = await apiGet<Single<Kurikulum>>(`/kurikulum/${id}`);
    kurikulum = res.data;
  } catch {
    notFound();
  }

  let list: Paginated<MataKuliah> | null = null;
  try {
    list = await apiGet<Paginated<MataKuliah>>("/mata-kuliah", {
      kurikulum_id: id,
      sort,
      dir,
      page,
      jenis_mk: sp.jenis_mk,
      semester: sp.semester,
      q: sp.q,
      per_page: 15,
    });
  } catch {
    list = null;
  }

  // Daftar Program Studi untuk penautan mata kuliah.
  let prodiOptions: { value: string; label: string }[] = [];
  try {
    const inst = await apiGet<{ data: InstitusiData[] }>("/institusi");
    prodiOptions = inst.data
      .filter((i) => i.jenis === "prodi")
      .map((i) => ({ value: String(i.id), label: i.nama }));
  } catch {
    prodiOptions = [];
  }

  const params2 = { sort, dir, jenis_mk: sp.jenis_mk, semester: sp.semester, q: sp.q };
  const basePath = `/kurikulum/${id}/mata-kuliah`;

  return (
    <div>
      <PageHeader
        title={`Mata Kuliah — ${kurikulum.nama}`}
        subtitle="Kelola daftar mata kuliah pada kurikulum ini."
        actions={
          <>
            <ImportExcelButton
              jenis="mata_kuliah"
              kurikulumId={kurikulum.id}
              institusiId={kurikulum.institusi_id}
              label="Mata Kuliah"
              fields={[
                { name: "kode_mk", wajib: true },
                { name: "nama", wajib: true },
                { name: "jenis_mk" },
                { name: "sifat" },
                { name: "sks_teori" },
                { name: "sks_praktik" },
                { name: "semester" },
                { name: "rumpun" },
                { name: "prodi_kode" },
                { name: "prasyarat_kode" },
              ]}
              contoh={"kode_mk,nama,sks_teori,sks_praktik,semester\nFAR101,Kimia Dasar,2,1,1"}
            />
            <CreateMkButton kurikulumId={kurikulum.id} prodiOptions={prodiOptions} />
          </>
        }
      />
      <KurikulumTabs id={id} active="mata-kuliah" />

      {!list || list.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada mata kuliah" hint="Tambahkan mata kuliah atau impor lewat Onboarding." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Kode" column="kode_mk" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <SortableTh label="Nama" column="nama" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <Th>Prodi</Th>
                <SortableTh label="Jenis" column="jenis_mk" sort={sort} dir={dir} basePath={basePath} params={params2} />
                <Th className="text-right">SKS</Th>
                <SortableTh label="Smt" column="semester" sort={sort} dir={dir} basePath={basePath} params={params2} className="text-right" />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((m) => (
                <tr key={m.id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{m.kode_mk}</Td>
                  <Td className="max-w-sm">
                    <p className="text-ink">{m.nama}</p>
                    {m.sifat && <p className="text-xs text-muted capitalize">{m.sifat}</p>}
                  </Td>
                  <Td>{m.institusi_nama ? <span className="text-ink">{m.institusi_nama}</span> : <span className="text-amber-600">Belum ditautkan</span>}</Td>
                  <Td>{m.jenis_mk ? <Badge tone="neutral">{JENIS_LABEL[m.jenis_mk] ?? m.jenis_mk}</Badge> : <span className="text-muted">—</span>}</Td>
                  <Td className="text-right tabular-nums">{m.sks ?? (m.sks_teori ?? 0) + (m.sks_praktik ?? 0)}</Td>
                  <Td className="text-right tabular-nums">{m.semester ?? "—"}</Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditMkButton m={m} kurikulumId={kurikulum.id} prodiOptions={prodiOptions} />
                      <DeleteMkButton m={m} kurikulumId={kurikulum.id} />
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
