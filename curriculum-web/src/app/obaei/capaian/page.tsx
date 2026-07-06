import { apiGet, type Paginated, type CapaianMahasiswa } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { TambahCapaian, EditCapaian, HapusCapaian } from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string }>;

const DEFAULT_INSTITUSI = 1;
const basePath = "/obaei/capaian";

function fmt(n: number | null): string {
  if (n === null || n === undefined) return "—";
  return Number.isInteger(n) ? String(n) : n.toFixed(2);
}

export default async function CapaianMahasiswaPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  const res = await apiGet<Paginated<CapaianMahasiswa>>("/capaian-mahasiswa", {
    institusi_id: DEFAULT_INSTITUSI,
    sort,
    dir,
    page,
    per_page: 15,
  }).catch(() => null);

  const params = { sort, dir };

  return (
    <div>
      <PageHeader
        title="Capaian Mahasiswa"
        subtitle="Rekap hasil pengukuran per CPMK/Sub-CPMK tiap mata kuliah. Data ini diagregasi menjadi ketercapaian CPL."
        actions={<TambahCapaian />}
      />

      {!res || res.data.length === 0 ? (
        <Card>
          <EmptyState
            title="Belum ada data capaian"
            hint="Tambahkan rekap capaian per CPMK dengan tombol “+ Data Capaian”."
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Kode MK" column="kode_mk" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th>CPMK / Sub-CPMK</Th>
                <SortableTh label="Angkatan" column="angkatan" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th className="text-right">Jml Mhs</Th>
                <SortableTh label="Nilai Rata²" column="nilai_rata_rata" sort={sort} dir={dir} basePath={basePath} params={params} className="text-right" />
                <SortableTh label="% Capaian" column="persentase_capaian_minimal" sort={sort} dir={dir} basePath={basePath} params={params} className="text-right" />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {res.data.map((c) => (
                <tr key={c.id} className="align-top hover:bg-gray-50">
                  <Td className="font-medium text-ink">{c.kode_mk}</Td>
                  <Td className="text-xs text-muted">
                    {c.cpmk ?? (c.cpmk_id ? `CPMK #${c.cpmk_id}` : null) ?? "—"}
                    {c.sub_cpmk && <span className="block">Sub: {c.sub_cpmk}</span>}
                    {!c.sub_cpmk && c.sub_cpmk_id ? <span className="block">Sub-CPMK #{c.sub_cpmk_id}</span> : null}
                  </Td>
                  <Td>{c.angkatan ? <Badge tone="neutral">{c.angkatan}</Badge> : <span className="text-muted">—</span>}</Td>
                  <Td className="text-right tabular-nums">{c.jumlah_mahasiswa ?? "—"}</Td>
                  <Td className="text-right tabular-nums">{fmt(c.nilai_rata_rata)}</Td>
                  <Td className="text-right font-medium tabular-nums">{fmt(c.persentase_capaian_minimal)}%</Td>
                  <Td>
                    <div className="flex justify-end gap-1.5">
                      <EditCapaian capaian={c} />
                      <HapusCapaian id={c.id} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={res.meta} basePath={basePath} params={params} />
        </Card>
      )}
    </div>
  );
}
