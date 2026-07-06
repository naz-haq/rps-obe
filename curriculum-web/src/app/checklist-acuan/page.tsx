import Link from "next/link";
import { apiGet, type Paginated, type KerangkaAcuan, type BadanRujukan } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, EmptyState } from "@/components/ui";
import { CreateKerangkaButton, EditKerangkaButton, DeleteKerangkaButton } from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; q?: string }>;

const DEFAULT_INSTITUSI = 1;

export default async function ChecklistAcuanPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "nama";
  const dir = sp.dir ?? "asc";
  const page = sp.page ?? "1";

  const [list, badanRes] = await Promise.all([
    apiGet<Paginated<KerangkaAcuan>>("/kerangka-acuan", { sort, dir, page, q: sp.q, per_page: 15 }).catch(() => null),
    apiGet<Paginated<BadanRujukan>>("/badan-rujukan", { institusi_id: DEFAULT_INSTITUSI, per_page: 100 }).catch(() => null),
  ]);

  const badanList = badanRes?.data ?? [];
  const params = { sort, dir, q: sp.q };
  const basePath = "/checklist-acuan";

  return (
    <div>
      <PageHeader
        title="Checklist Penyelarasan Acuan"
        subtitle="Bandingkan kurikulum terhadap kerangka acuan (KPT, asosiasi, akreditasi) dan tandai status pemenuhan tiap butir."
        actions={<CreateKerangkaButton badanList={badanList} />}
      />

      {!list || list.data.length === 0 ? (
        <Card>
          <EmptyState
            title="Belum ada kerangka acuan"
            hint={badanList.length === 0 ? "Tambahkan Badan Rujukan di menu Dokumen Rujukan, lalu buat kerangka acuan." : "Buat kerangka acuan untuk mulai menyusun checklist."}
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Nama" column="nama" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th>Badan</Th>
                <SortableTh label="Versi" column="versi" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Butir" column="butir_count" sort={sort} dir={dir} basePath={basePath} params={params} className="text-right" />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((k) => (
                <tr key={k.id} className="hover:bg-gray-50">
                  <Td>
                    <Link href={`/checklist-acuan/${k.id}`} className="font-medium text-brand-700 hover:underline">
                      {k.nama}
                    </Link>
                  </Td>
                  <Td className="text-muted">{k.badan_rujukan ?? "—"}</Td>
                  <Td>{k.versi ?? "—"}</Td>
                  <Td className="text-right tabular-nums">{k.butir_count ?? 0}</Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditKerangkaButton k={k} badanList={badanList} />
                      <DeleteKerangkaButton k={k} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={list.meta} basePath={basePath} params={params} />
        </Card>
      )}
    </div>
  );
}
