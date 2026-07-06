import { apiGet, type Paginated, type Cpl, type TargetCpl } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { TambahTarget, EditTarget, HapusTarget } from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string }>;

const DEFAULT_INSTITUSI = 1;
const basePath = "/obaei/target";

function fmt(n: number | null): string {
  if (n === null || n === undefined) return "—";
  return Number.isInteger(n) ? String(n) : n.toFixed(2);
}

export default async function TargetCplPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  const [res, cplRes] = await Promise.all([
    apiGet<Paginated<TargetCpl>>("/target-cpl", {
      institusi_id: DEFAULT_INSTITUSI,
      sort,
      dir,
      page,
      per_page: 15,
    }).catch(() => null),
    apiGet<Paginated<Cpl>>("/cpl", { institusi_id: DEFAULT_INSTITUSI, per_page: 200 }).catch(() => null),
  ]);

  const cplOptions = (cplRes?.data ?? []).map((c) => ({
    value: String(c.id),
    label: `${c.kode} — ${c.deskripsi}`,
  }));
  const params = { sort, dir };

  return (
    <div>
      <PageHeader
        title="Target CPL"
        subtitle="Tetapkan ambang kelulusan dan persentase target ketercapaian tiap CPL. Nilai ini menjadi acuan penilaian OBAEI."
        actions={<TambahTarget cplOptions={cplOptions} />}
      />

      {!res || res.data.length === 0 ? (
        <Card>
          <EmptyState
            title="Belum ada target CPL"
            hint="Tambahkan target dengan tombol “+ Target CPL”. Satu CPL dapat memiliki target berbeda per angkatan."
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>CPL</Th>
                <SortableTh label="Angkatan" column="angkatan" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Ambang" column="ambang_nilai" sort={sort} dir={dir} basePath={basePath} params={params} className="text-right" />
                <SortableTh label="% Target" column="persentase_target" sort={sort} dir={dir} basePath={basePath} params={params} className="text-right" />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {res.data.map((t) => (
                <tr key={t.id} className="align-top hover:bg-gray-50">
                  <Td>
                    <p className="font-medium text-ink">{t.cpl?.kode ?? `CPL #${t.cpl_id}`}</p>
                    {t.cpl?.deskripsi && <p className="max-w-md text-xs text-muted">{t.cpl.deskripsi}</p>}
                  </Td>
                  <Td>{t.angkatan ? <Badge tone="neutral">{t.angkatan}</Badge> : <span className="text-muted">Semua</span>}</Td>
                  <Td className="text-right tabular-nums">{fmt(t.ambang_nilai)}</Td>
                  <Td className="text-right tabular-nums">{fmt(t.persentase_target)}%</Td>
                  <Td>
                    <div className="flex justify-end gap-1.5">
                      <EditTarget target={t} cplOptions={cplOptions} />
                      <HapusTarget id={t.id} />
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
