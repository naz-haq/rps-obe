import Link from "next/link";
import { apiGet, resolvePerPage, type Paginated, type RpsVersion } from "@/lib/api";
import { rpsStatusLabel, rpsStatusTone } from "@/lib/rps-status";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState, SearchBox } from "@/components/ui";
import { DeleteRpsButton } from "./delete-button";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string; per_page?: string; q?: string }>;

export default async function RpsPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";
  const perPage = resolvePerPage(sp.per_page);

  let list: Paginated<RpsVersion> | null = null;
  let error: string | null = null;
  try {
    list = await apiGet<Paginated<RpsVersion>>("/rps-versions", { sort, dir, page, status: sp.status, q: sp.q, per_page: perPage });
  } catch {
    error = "Tidak dapat memuat dokumen RPS. Pastikan backend berjalan di :8100.";
  }

  const params = { sort, dir, status: sp.status, q: sp.q, per_page: String(perPage) };

  return (
    <div>
      <PageHeader
        title="Dokumen RPS"
        subtitle="Versi RPS resmi hasil commit generator, lengkap dengan traceability OBE."
      />

      <SearchBox basePath="/rps" q={sp.q} params={params} className="mb-3" />

      {error ? (
        <Card>
          <div className="p-5 text-sm text-red-600">{error}</div>
        </Card>
      ) : !list || list.data.length === 0 ? (
        <Card>
          <EmptyState
            title={sp.q ? `Tidak ada dokumen RPS cocok “${sp.q}”` : "Belum ada dokumen RPS"}
            hint={sp.q ? "Coba kata kunci lain atau bersihkan pencarian." : "Commit sebuah sesi generator untuk membuat versi RPS."}
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Mata Kuliah" column="kode_mk" sort={sort} dir={dir} basePath="/rps" params={params} />
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
                  <Td>
                    <p className="font-medium text-ink">{r.nama_mk ?? r.kode_mk}</p>
                    {r.nama_mk && <p className="text-xs text-muted">{r.kode_mk}</p>}
                  </Td>
                  <Td><Badge tone="brand">v{r.versi}</Badge></Td>
                  <Td className="text-right tabular-nums">{r.minggu_count ?? 0}</Td>
                  <Td className="text-right tabular-nums">{r.komponen_count ?? 0}</Td>
                  <Td className="uppercase text-muted">{r.bahasa}</Td>
                  <Td><Badge tone={rpsStatusTone(r.status)}>{rpsStatusLabel(r.status)}</Badge></Td>
                  <Td className="text-right">
                    <div className="flex items-center justify-end gap-3">
                      <Link href={`/rps/${r.id}`} className="text-sm font-medium text-brand-700 hover:underline">
                        Buka →
                      </Link>
                      <DeleteRpsButton rps={r} />
                    </div>
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
