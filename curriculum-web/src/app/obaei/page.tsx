import { apiGet, type Single, type ObaeiAgregasi, type ObaeiStatus } from "@/lib/api";
import { PageHeader, Card, CardBody, Table, Th, Td, Badge, EmptyState, Stat } from "@/components/ui";

type SearchParams = Promise<{ angkatan?: string }>;

const DEFAULT_INSTITUSI = 1;

const STATUS_TONE: Record<ObaeiStatus, "ok" | "warn" | "danger" | "neutral"> = {
  tercapai: "ok",
  belum: "danger",
  tanpa_data: "neutral",
  tanpa_target: "warn",
};
const STATUS_LABEL: Record<ObaeiStatus, string> = {
  tercapai: "Tercapai",
  belum: "Belum Tercapai",
  tanpa_data: "Tanpa Data",
  tanpa_target: "Target Belum Diset",
};

function fmt(n: number | null, suffix = ""): string {
  if (n === null) return "—";
  return `${Number.isInteger(n) ? n : n.toFixed(2)}${suffix}`;
}

export default async function ObaeiDashboardPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const angkatan = sp.angkatan ?? "";

  const res = await apiGet<Single<ObaeiAgregasi>>("/obaei/agregasi", {
    institusi_id: DEFAULT_INSTITUSI,
    angkatan: angkatan || undefined,
  }).catch(() => null);

  const agg = res?.data;
  const rows = agg?.cpl ?? [];
  const r = agg?.ringkasan;

  return (
    <div>
      <PageHeader
        title="Ketercapaian CPL (OBAEI)"
        subtitle="Agregasi capaian mahasiswa dibandingkan target CPL untuk menutup siklus OBC → OBLT → OBAEI. Data ini menjadi dasar evaluasi & tindak lanjut perbaikan RPS."
        actions={
          <form className="flex items-center gap-2">
            <input
              name="angkatan"
              defaultValue={angkatan}
              placeholder="Filter angkatan (mis. 2024)"
              className="w-48 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
            />
            <button type="submit" className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
              Terapkan
            </button>
          </form>
        }
      />

      {r && (
        <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Stat label="Total CPL" value={r.total_cpl} />
          <Stat label="Tercapai" value={r.tercapai} hint={`${r.persen_tercapai}% dari total`} />
          <Stat label="Belum Tercapai" value={r.belum} />
          <Stat label="Tanpa Data" value={r.tanpa_data} />
        </div>
      )}

      {rows.length === 0 ? (
        <Card>
          <EmptyState
            title="Belum ada data ketercapaian"
            hint="Isi Target CPL dan Capaian Mahasiswa terlebih dahulu, lalu kembali ke halaman ini."
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>CPL</Th>
                <Th className="text-right">Target (%)</Th>
                <Th className="text-right">Ambang</Th>
                <Th className="text-right">Capaian (%)</Th>
                <Th className="text-right">Selisih</Th>
                <Th className="text-right">Mahasiswa</Th>
                <Th>Status</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.cpl_id} className="align-top hover:bg-gray-50">
                  <Td>
                    <p className="font-medium text-ink">{row.kode}</p>
                    <p className="max-w-md text-xs text-muted">{row.deskripsi}</p>
                  </Td>
                  <Td className="text-right tabular-nums">{fmt(row.target_persen)}</Td>
                  <Td className="text-right tabular-nums">{fmt(row.ambang_nilai)}</Td>
                  <Td className="text-right font-medium tabular-nums">{fmt(row.capaian_persen)}</Td>
                  <Td className="text-right tabular-nums">
                    {row.selisih === null ? (
                      "—"
                    ) : (
                      <span className={row.selisih >= 0 ? "text-emerald-600" : "text-red-600"}>
                        {row.selisih >= 0 ? "+" : ""}
                        {fmt(row.selisih)}
                      </span>
                    )}
                  </Td>
                  <Td className="text-right tabular-nums text-muted">
                    {row.jumlah_mahasiswa || "—"}
                    {row.jumlah_komponen > 0 && (
                      <span className="block text-xs">{row.jumlah_komponen} komponen</span>
                    )}
                  </Td>
                  <Td>
                    <Badge tone={STATUS_TONE[row.status]}>{STATUS_LABEL[row.status]}</Badge>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <CardBody>
            <p className="text-xs text-muted">
              Capaian = rata-rata persentase mahasiswa yang mencapai ambang, tertimbang jumlah mahasiswa dari
              seluruh CPMK yang mengampu CPL tersebut. Status “Tercapai” bila capaian ≥ target.
            </p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
