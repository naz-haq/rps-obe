import Link from "next/link";
import { apiGet, type Paginated, type RpsVersion } from "@/lib/api";
import { rpsStatusLabel, rpsStatusTone } from "@/lib/rps-status";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { TinjauActions } from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string }>;

const basePath = "/persetujuan";

const STATUS_FILTER: { value: string; label: string }[] = [
  { value: "review", label: "Menunggu Tinjauan" },
  { value: "revisi", label: "Perlu Revisi" },
  { value: "approved", label: "Disetujui" },
  { value: "draft", label: "Draf" },
];

export default async function PersetujuanPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const status = sp.status ?? "review";
  const sort = sp.sort ?? "submitted_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  const list = await apiGet<Paginated<RpsVersion>>("/persetujuan", {
    status,
    sort,
    dir,
    page,
    per_page: 15,
  }).catch(() => null);

  const params = { status, sort, dir };

  return (
    <div>
      <PageHeader
        title="Persetujuan RPS"
        subtitle="Antrian tinjauan alur Dosen → Kaprodi/STPMP. Setujui untuk mengunci versi, atau minta revisi dengan catatan."
      />

      <div className="mb-4 flex flex-wrap gap-1.5">
        {STATUS_FILTER.map((f) => {
          const active = status === f.value;
          return (
            <a
              key={f.value}
              href={`${basePath}?status=${f.value}`}
              className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                active ? "bg-brand-600 text-white" : "border border-border bg-surface text-muted hover:bg-gray-50"
              }`}
            >
              {f.label}
            </a>
          );
        })}
      </div>

      {!list || list.data.length === 0 ? (
        <Card>
          <EmptyState
            title="Tidak ada RPS pada status ini"
            hint="Antrian tinjauan kosong. RPS yang diajukan dosen akan muncul di sini."
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Kode MK" column="kode_mk" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Versi" column="versi" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th className="text-right">Minggu</Th>
                <Th className="text-right">Komponen</Th>
                <SortableTh label="Diajukan" column="submitted_at" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Status" column="status" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((r) => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">
                    <Link href={`/rps/${r.id}`} className="text-brand-700 hover:underline">{r.kode_mk}</Link>
                  </Td>
                  <Td><Badge tone="brand">v{r.versi}</Badge></Td>
                  <Td className="text-right tabular-nums">{r.minggu_count ?? 0}</Td>
                  <Td className="text-right tabular-nums">{r.komponen_count ?? 0}</Td>
                  <Td className="text-muted">
                    {r.submitted_at ? new Date(r.submitted_at).toLocaleDateString("id-ID") : "—"}
                  </Td>
                  <Td><Badge tone={rpsStatusTone(r.status)}>{rpsStatusLabel(r.status)}</Badge></Td>
                  <Td className="text-right">
                    {r.status === "review" ? (
                      <TinjauActions id={r.id} />
                    ) : (
                      <Link href={`/rps/${r.id}`} className="text-sm font-medium text-brand-700 hover:underline">
                        Buka →
                      </Link>
                    )}
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
