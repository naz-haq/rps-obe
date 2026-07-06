import Link from "next/link";
import { apiGet, type Single, type GovRingkasan, type GovPenggunaan } from "@/lib/api";
import { PageHeader, Card, CardBody, Table, Th, Td, Badge, EmptyState, Stat, buttonClass } from "@/components/ui";

type SearchParams = Promise<{ hari?: string }>;

const DEFAULT_INSTITUSI = 1;

const PERIODE = [
  { value: "7", label: "7 hari" },
  { value: "30", label: "30 hari" },
  { value: "90", label: "90 hari" },
  { value: "365", label: "1 tahun" },
];

function usd(n: number): string {
  return `$${n.toFixed(4)}`;
}
function ribu(n: number): string {
  return n.toLocaleString("id-ID");
}

function BarRow({ label, value, max, hint }: { label: string; value: number; max: number; hint?: string }) {
  const pct = max > 0 ? Math.round((value / max) * 100) : 0;
  return (
    <div className="flex items-center gap-3">
      <span className="w-40 shrink-0 truncate text-xs text-ink" title={label}>{label}</span>
      <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100">
        <div className="h-full rounded-full bg-brand-500" style={{ width: `${pct}%` }} />
      </div>
      <span className="w-24 shrink-0 text-right text-xs tabular-nums text-muted">{hint ?? ribu(value)}</span>
    </div>
  );
}

export default async function GovernanceDashboardPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const hari = sp.hari ?? "90";

  const [ringkasanRes, penggunaanRes] = await Promise.all([
    apiGet<Single<GovRingkasan>>("/governance/ringkasan", { institusi_id: DEFAULT_INSTITUSI, hari }).catch(() => null),
    apiGet<Single<GovPenggunaan>>("/governance/penggunaan", { institusi_id: DEFAULT_INSTITUSI, hari }).catch(() => null),
  ]);

  const r = ringkasanRes?.data;
  const p = penggunaanRes?.data;

  const maxModeJml = Math.max(1, ...(p?.per_mode ?? []).map((m) => m.jumlah));
  const maxModelBiaya = Math.max(0.0000001, ...(p?.per_model ?? []).map((m) => m.biaya));
  const maxHariBiaya = Math.max(0.0000001, ...(p?.per_hari ?? []).map((d) => d.biaya));

  return (
    <div>
      <PageHeader
        title="Dashboard Biaya & Penggunaan AI"
        subtitle="Monitoring token, biaya, dan komposisi pemanggilan AI per tenant. Biaya dihitung dari tabel harga model × token (dilog di setiap interaksi)."
        actions={
          <div className="flex gap-1.5">
            {PERIODE.map((f) => (
              <Link
                key={f.value}
                href={`/governance?hari=${f.value}`}
                className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                  hari === f.value ? "bg-brand-600 text-white" : "border border-border bg-surface text-muted hover:bg-gray-50"
                }`}
              >
                {f.label}
              </Link>
            ))}
          </div>
        }
      />

      {!r ? (
        <Card>
          <EmptyState title="Data tidak tersedia" hint="Pastikan layanan backend berjalan." />
        </Card>
      ) : (
        <>
          <div className="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Stat label="Total Biaya" value={usd(r.total_biaya)} hint={`${r.periode_hari} hari terakhir`} />
            <Stat label="Total Token" value={ribu(r.tokens_total)} hint={`${ribu(r.tokens_in)} in · ${ribu(r.tokens_out)} out`} />
            <Stat label="Interaksi AI" value={ribu(r.total_interaksi)} hint={`${r.success_rate}% sukses`} />
            <Stat label="Gagal / Audit" value={`${r.gagal} / ${r.total_audit}`} hint={`${r.notifikasi_unread} notifikasi belum dibaca`} />
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardBody>
                <h3 className="mb-3 text-sm font-semibold text-ink">Penggunaan per Mode</h3>
                {(p?.per_mode ?? []).length === 0 ? (
                  <p className="text-sm text-muted">Belum ada data.</p>
                ) : (
                  <div className="space-y-2">
                    {p!.per_mode.map((m) => (
                      <BarRow key={m.mode} label={m.mode} value={m.jumlah} max={maxModeJml} hint={`${m.jumlah}× · ${usd(m.biaya)}`} />
                    ))}
                  </div>
                )}
              </CardBody>
            </Card>

            <Card>
              <CardBody>
                <h3 className="mb-3 text-sm font-semibold text-ink">Biaya per Model</h3>
                {(p?.per_model ?? []).length === 0 ? (
                  <p className="text-sm text-muted">Belum ada data.</p>
                ) : (
                  <div className="space-y-2">
                    {p!.per_model.map((m) => (
                      <BarRow key={m.model} label={m.model} value={m.biaya} max={maxModelBiaya} hint={`${usd(m.biaya)} · ${ribu(m.tokens)} tok`} />
                    ))}
                  </div>
                )}
              </CardBody>
            </Card>
          </div>

          <Card className="mt-4">
            <CardBody>
              <h3 className="mb-3 text-sm font-semibold text-ink">Tren Harian</h3>
              {(p?.per_hari ?? []).length === 0 ? (
                <p className="text-sm text-muted">Belum ada data.</p>
              ) : (
                <Table>
                  <thead>
                    <tr>
                      <Th>Tanggal</Th>
                      <Th className="text-right">Interaksi</Th>
                      <Th className="text-right">Token</Th>
                      <Th className="text-right">Biaya</Th>
                      <Th className="w-1/3">Proporsi Biaya</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {p!.per_hari.map((d) => {
                      const pct = maxHariBiaya > 0 ? Math.round((d.biaya / maxHariBiaya) * 100) : 0;
                      return (
                        <tr key={d.tanggal} className="hover:bg-gray-50">
                          <Td>{d.tanggal}</Td>
                          <Td className="text-right tabular-nums">{ribu(d.jumlah)}</Td>
                          <Td className="text-right tabular-nums">{ribu(d.tokens)}</Td>
                          <Td className="text-right tabular-nums">{usd(d.biaya)}</Td>
                          <Td>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                              <div className="h-full rounded-full bg-emerald-500" style={{ width: `${pct}%` }} />
                            </div>
                          </Td>
                        </tr>
                      );
                    })}
                  </tbody>
                </Table>
              )}
            </CardBody>
          </Card>

          <div className="mt-4 flex flex-wrap items-center gap-3">
            {(p?.per_status ?? []).map((s) => (
              <Badge key={s.status} tone={s.status === "sukses" ? "ok" : s.status === "gagal" ? "danger" : "neutral"}>
                {s.status}: {s.jumlah}
              </Badge>
            ))}
            <Link href="/governance/audit" className={buttonClass("secondary", "sm")}>Lihat Audit Log →</Link>
          </div>
        </>
      )}
    </div>
  );
}
