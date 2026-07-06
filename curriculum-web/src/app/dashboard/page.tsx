import Link from "next/link";
import { apiGet, type Paginated, type Kurikulum, type RpsVersion, type GenerateSession } from "@/lib/api";
import { PageHeader, Card, CardBody, Stat, Badge, LinkButton, EmptyState } from "@/components/ui";

async function safeCount(path: string): Promise<{ total: number; data: unknown[] }> {
  try {
    const res = await apiGet<Paginated<unknown>>(path, { per_page: 5 });
    return { total: res.meta?.total ?? res.data.length, data: res.data };
  } catch {
    return { total: 0, data: [] };
  }
}

export default async function DashboardPage() {
  const [kur, rps, sesi] = await Promise.all([
    safeCount("/kurikulum"),
    safeCount("/rps-versions"),
    safeCount("/generate-sessions"),
  ]);
  const kurikulum = kur.data as Kurikulum[];
  const rpsList = rps.data as RpsVersion[];
  const sesiList = sesi.data as GenerateSession[];
  const sesiBerjalan = sesiList.filter((s) => s.status !== "committed").length;

  return (
    <div>
      <PageHeader
        title="Beranda"
        subtitle="Ringkasan kurikulum, sesi generator, dan dokumen RPS."
        actions={<LinkButton href="/generator">+ Sesi RPS Baru</LinkButton>}
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Kurikulum" value={kur.total} hint="Payload kurikulum aktif & arsip" />
        <Stat label="Dokumen RPS" value={rps.total} hint="Versi RPS ter-commit" />
        <Stat label="Sesi Generator" value={sesi.total} hint={`${sesiBerjalan} berjalan`} />
        <Stat label="Profil AI" value={<ProfilBadge />} hint="Jalur model aktif" />
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card className="animate-fade-up">
          <div className="flex items-center justify-between border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Kurikulum Terbaru</h2>
            <Link href="/kurikulum" className="text-xs font-medium text-brand-700 hover:underline">
              Lihat semua
            </Link>
          </div>
          {kurikulum.length === 0 ? (
            <EmptyState title="Belum ada kurikulum" hint="Mulai dari Onboarding untuk mengimpor CPL & mata kuliah." />
          ) : (
            <ul className="divide-y divide-border">
              {kurikulum.map((k) => (
                <li key={k.id}>
                  <Link href={`/kurikulum/${k.id}`} className="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium text-ink">{k.nama}</p>
                      <p className="text-xs text-muted">
                        {k.kode ? `${k.kode} · ` : ""}Tahun {k.tahun}
                      </p>
                    </div>
                    <StatusBadge status={k.status} />
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card className="animate-fade-up">
          <div className="flex items-center justify-between border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Dokumen RPS Terbaru</h2>
            <Link href="/rps" className="text-xs font-medium text-brand-700 hover:underline">
              Lihat semua
            </Link>
          </div>
          {rpsList.length === 0 ? (
            <EmptyState title="Belum ada RPS" hint="Buat lewat Generator RPS lalu commit hasilnya." />
          ) : (
            <ul className="divide-y divide-border">
              {rpsList.map((r) => (
                <li key={r.id}>
                  <Link href={`/rps/${r.id}`} className="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium text-ink">{r.kode_mk}</p>
                      <p className="text-xs text-muted">
                        Versi {r.versi} · {r.minggu_count ?? 0} minggu · {r.komponen_count ?? 0} komponen
                      </p>
                    </div>
                    <Badge tone="brand">{r.status}</Badge>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      <Card className="mt-6 animate-fade-up">
        <CardBody className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h3 className="text-sm font-semibold text-ink">Alur kerja OBE</h3>
            <p className="mt-1 text-sm text-muted">
              Peta Kurikulum (CPL × MK) → Generator RPS bertahap → Dokumen RPS + traceability.
            </p>
          </div>
          <div className="flex gap-2">
            <LinkButton href="/kurikulum" variant="secondary">Peta Kurikulum</LinkButton>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

async function ProfilBadge() {
  try {
    const res = await apiGet<{ data: { profil_aktif: string } }>("/ai/pengaturan");
    const p = res.data.profil_aktif;
    return <span className="capitalize">{p}</span>;
  } catch {
    return <span className="text-muted">—</span>;
  }
}

function StatusBadge({ status }: { status: string }) {
  const tone = status === "berlaku" ? "ok" : status === "arsip" ? "neutral" : "warn";
  return <Badge tone={tone}>{status}</Badge>;
}
