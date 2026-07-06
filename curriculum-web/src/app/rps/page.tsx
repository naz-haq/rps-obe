import Link from "next/link";
import { apiGet, type Paginated, type RpsVersion } from "@/lib/api";
import { rpsStatusLabel, rpsStatusTone } from "@/lib/rps-status";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string }>;

export default async function RpsPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  let list: Paginated<RpsVersion> | null = null;
  let error: string | null = null;
  try {
    list = await apiGet<Paginated<RpsVersion>>("/rps-versions", { sort, dir, page, status: sp.status, per_page: 15 });
  } catch {
    error = "Tidak dapat memuat dokumen RPS. Pastikan backend berjalan di :8100.";
  }

  const params = { sort, dir, status: sp.status };

  return (
    <div>
      <PageHeader
        title="Dokumen RPS"
        subtitle="Versi RPS resmi hasil commit generator, lengkap dengan traceability OBE."
      />

      {error ? (
        <Card>
          <div className="p-5 text-sm text-red-600">{error}</div>
        </Card>
      ) : !list || list.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada dokumen RPS" hint="Commit sebuah sesi generator untuk membuat versi RPS." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Kode MK" column="kode_mk" sort={sort} dir={dir} basePath="/rps" params={params} />
                <SortableTh label="Versi" column="versi" sort={sort} dir={dir} basePath="/rps" params={params} />
                <Th className="text-right">Minggu</Th>
                <Th className="text-right">Komponen</Th>
                <Th>Bahasa</Th>
                <SortableTh label="Status" column="status" sort={sort} dir={dir} basePath="/rps" params={params} />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((r) => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{r.kode_mk}</Td>
                  <Td><Badge tone="brand">v{r.versi}</Badge></Td>
                  <Td className="text-right tabular-nums">{r.minggu_count ?? 0}</Td>
                  <Td className="text-right tabular-nums">{r.komponen_count ?? 0}</Td>
                  <Td className="uppercase text-muted">{r.bahasa}</Td>
                  <Td><Badge tone={rpsStatusTone(r.status)}>{rpsStatusLabel(r.status)}</Badge></Td>
                  <Td className="text-right">
                    <Link href={`/rps/${r.id}`} className="text-sm font-medium text-brand-700 hover:underline">
                      Buka →
                    </Link>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={list.meta} basePath="/rps" params={params} />
        </Card>
      )}
    </div>
  );
}
