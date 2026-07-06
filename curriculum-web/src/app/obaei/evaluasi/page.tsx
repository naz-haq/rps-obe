import Link from "next/link";
import { apiGet, type Paginated, type Cpl, type EvaluasiCpl } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState, buttonClass } from "@/components/ui";
import { BuatEvaluasi, HapusEvaluasi } from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string }>;

const DEFAULT_INSTITUSI = 1;
const basePath = "/obaei/evaluasi";

const STATUS_FILTER = [
  { value: "", label: "Semua" },
  { value: "draft", label: "Draft" },
  { value: "final", label: "Final" },
];

export default async function EvaluasiListPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  const [res, cplRes] = await Promise.all([
    apiGet<Paginated<EvaluasiCpl>>("/evaluasi-cpl", {
      institusi_id: DEFAULT_INSTITUSI,
      sort,
      dir,
      page,
      status: sp.status,
      per_page: 15,
    }).catch(() => null),
    apiGet<Paginated<Cpl>>("/cpl", { institusi_id: DEFAULT_INSTITUSI, per_page: 200 }).catch(() => null),
  ]);

  const cplOptions = (cplRes?.data ?? []).map((c) => ({
    value: String(c.id),
    label: `${c.kode} — ${c.deskripsi}`,
  }));
  const params = { sort, dir, status: sp.status };

  return (
    <div>
      <PageHeader
        title="Evaluasi & Tindak Lanjut"
        subtitle="Dokumentasikan evaluasi ketercapaian CPL, gunakan AI Copilot untuk menyusun narasi & rekomendasi tindak lanjut, lalu finalisasi sebagai bukti closing the loop."
        actions={<BuatEvaluasi cplOptions={cplOptions} />}
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
            title="Belum ada evaluasi CPL"
            hint="Buat evaluasi baru untuk mendokumentasikan analisis ketercapaian dan tindak lanjut perbaikan."
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>CPL</Th>
                <Th>Periode</Th>
                <Th className="text-right">Tindak Lanjut</Th>
                <SortableTh label="Status" column="status" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Dibuat" column="created_at" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {res.data.map((e) => (
                <tr key={e.id} className="align-top hover:bg-gray-50">
                  <Td>
                    <Link href={`/obaei/evaluasi/${e.id}`} className="font-medium text-brand-700 hover:underline">
                      {e.cpl?.kode ?? `CPL #${e.cpl_id}`}
                    </Link>
                    {e.cpl?.deskripsi && <p className="max-w-md text-xs text-muted">{e.cpl.deskripsi}</p>}
                  </Td>
                  <Td className="text-muted">{e.periode ?? "—"}</Td>
                  <Td className="text-right tabular-nums">{e.tindak_lanjut_count ?? 0}</Td>
                  <Td>
                    <Badge tone={e.status === "final" ? "ok" : "warn"}>
                      {e.status === "final" ? "Final" : "Draft"}
                    </Badge>
                  </Td>
                  <Td className="text-xs text-muted">{e.created_at?.slice(0, 10) ?? "—"}</Td>
                  <Td>
                    <div className="flex justify-end gap-1.5">
                      <Link href={`/obaei/evaluasi/${e.id}`} className={buttonClass("ghost", "sm")}>
                        Buka
                      </Link>
                      {e.status !== "final" && <HapusEvaluasi id={e.id} />}
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
