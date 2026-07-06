import { apiGet, type Paginated, type ValidasiOverlap } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { PindaiButton, AnalisisButton, ReviewButton } from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string }>;

const DEFAULT_INSTITUSI = 1;
const basePath = "/validasi-overlap";

const STATUS_TONE: Record<string, "ok" | "warn" | "danger"> = {
  aman: "ok",
  perlu_review: "warn",
  overlap: "danger",
};
const STATUS_LABEL: Record<string, string> = {
  aman: "Aman",
  perlu_review: "Perlu Ditinjau",
  overlap: "Overlap",
};

const STATUS_FILTER: { value: string; label: string }[] = [
  { value: "", label: "Semua status" },
  { value: "overlap", label: "Overlap" },
  { value: "perlu_review", label: "Perlu Ditinjau" },
  { value: "aman", label: "Aman" },
];

export default async function ValidasiOverlapPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  const res = await apiGet<Paginated<ValidasiOverlap>>("/validasi-overlap", {
    institusi_id: DEFAULT_INSTITUSI,
    sort,
    dir,
    page,
    status: sp.status,
    per_page: 15,
  }).catch(() => null);

  const params = { sort, dir, status: sp.status };

  return (
    <div>
      <PageHeader
        title="Validator Overlap"
        subtitle="Deteksi keterampilan (butir bahan kajian) yang diklaim lebih dari satu mata kuliah. Bedakan penguatan bertingkat yang disengaja dari tumpang tindih yang boros SKS."
        actions={<PindaiButton />}
      />

      <div className="mb-4 flex flex-wrap gap-1.5">
        {STATUS_FILTER.map((f) => {
          const active = (sp.status ?? "") === f.value;
          const qs = new URLSearchParams();
          if (f.value) qs.set("status", f.value);
          return (
            <a
              key={f.value || "all"}
              href={`${basePath}${qs.toString() ? `?${qs.toString()}` : ""}`}
              className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                active ? "bg-brand-600 text-white" : "border border-border bg-surface text-muted hover:bg-gray-50"
              }`}
            >
              {f.label}
            </a>
          );
        })}
      </div>

      {!res || res.data.length === 0 ? (
        <Card>
          <EmptyState
            title="Belum ada temuan overlap"
            hint="Jalankan “Pindai Overlap” untuk memeriksa pemetaan keterampilan antar mata kuliah. Pastikan matriks MK–keterampilan sudah diisi."
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>Keterampilan</Th>
                <Th>Mata Kuliah Terlibat</Th>
                <SortableTh label="Status" column="status" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th>Rekomendasi</Th>
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {res.data.map((o) => (
                <tr key={o.id} className="hover:bg-gray-50 align-top">
                  <Td>
                    <p className="font-medium text-ink">{o.keterampilan?.deskripsi ?? "—"}</p>
                    {o.keterampilan?.bahan_kajian && (
                      <p className="text-xs text-muted">Bahan kajian: {o.keterampilan.bahan_kajian}</p>
                    )}
                    {o.analisis && <p className="mt-1 max-w-md text-xs text-muted">{o.analisis}</p>}
                  </Td>
                  <Td>
                    <div className="flex flex-wrap gap-1">
                      {o.mk_terlibat.map((m) => (
                        <span
                          key={m.kode_mk}
                          title={m.fokus_spesifik ?? undefined}
                          className="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
                        >
                          {m.kode_mk}
                        </span>
                      ))}
                    </div>
                    <p className="mt-1 text-xs text-muted">{o.jumlah_mk} mata kuliah</p>
                  </Td>
                  <Td>
                    <Badge tone={STATUS_TONE[o.status] ?? "neutral"}>{STATUS_LABEL[o.status] ?? o.status}</Badge>
                  </Td>
                  <Td className="max-w-xs text-xs text-muted">{o.rekomendasi ?? "—"}</Td>
                  <Td>
                    <div className="flex justify-end gap-1.5">
                      <AnalisisButton id={o.id} />
                      <ReviewButton overlap={o} />
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
