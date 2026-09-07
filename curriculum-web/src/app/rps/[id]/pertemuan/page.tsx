import Link from "next/link";
import { notFound } from "next/navigation";
import { apiGet, type Single, type RpsDetail } from "@/lib/api";
import { PageHeader, Card, Badge, Table, Th, Td, EmptyState } from "@/components/ui";
import { deteksiUjian } from "@/lib/rps-status";
import { GeneratePertemuanButton } from "./generate-button";

/**
 * Halaman rincian per-PERTEMUAN — detail operasional hasil generate lanjutan:
 * MK blok/profesi = pemecahan pekan jadi sesi harian; MK reguler = skenario
 * tahapan (pendahuluan-inti-penutup) + penugasan terstruktur & belajar mandiri.
 */
export default async function RincianPertemuanPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  let detail: RpsDetail;
  try {
    const res = await apiGet<Single<RpsDetail>>(`/rps-versions/${id}`);
    detail = res.data;
  } catch {
    notFound();
  }

  const { rps, minggu } = detail;
  const adaRincian = minggu.some((m) => (m.rincian_pertemuan?.length ?? 0) > 0);
  const sesiPerPekan = minggu.find((m) => (m.estimasi_waktu?.jumlah_pertemuan ?? 0) > 1)
    ?.estimasi_waktu?.jumlah_pertemuan;

  return (
    <div>
      <PageHeader
        title={`Rincian Pertemuan — ${rps.kode_mk} · v${rps.versi}`}
        subtitle={
          sesiPerPekan
            ? `Pemecahan rencana mingguan menjadi sesi per pertemuan (±${sesiPerPekan} pertemuan/pekan). Topik/aktivitas disusun AI dari materi pekan; durasi dihitung sistem.`
            : "Skenario operasional tiap pertemuan: tahapan pendahuluan → inti → penutup plus penugasan terstruktur & belajar mandiri. Kegiatan disusun AI dari materi pekan; alokasi menit dihitung sistem."
        }
        actions={
          <div className="flex items-center gap-3">
            <GeneratePertemuanButton id={rps.id} ada={adaRincian} />
            <Link href={`/rps/${rps.id}`} className="text-sm text-brand-700 hover:underline">
              ← Kembali ke RPS
            </Link>
          </div>
        }
      />

      {minggu.length === 0 ? (
        <EmptyState title="RPS belum memiliki rencana mingguan" />
      ) : !adaRincian ? (
        <Card>
          <EmptyState
            title="Belum ada rincian pertemuan"
            hint="Klik “Generate Rincian Pertemuan (AI)” — MK blok/profesi dipecah jadi sesi harian; MK reguler disusunkan skenario tahapan per pertemuan."
          />
        </Card>
      ) : (
        <div className="space-y-5">
          {minggu.map((m) => {
            const rincian = m.rincian_pertemuan ?? [];
            const jenisUjian = deteksiUjian(m.materi_pustaka);
            const evaluasi =
              jenisUjian === "uts"
                ? "Evaluasi Tengah Semester (UTS)"
                : jenisUjian === "uas"
                  ? "Evaluasi Akhir Semester (UAS)"
                  : null;

            return (
              <Card key={m.minggu_ke}>
                <div className={`border-b border-border px-5 py-3 ${evaluasi ? "bg-amber-50" : ""}`}>
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-sm font-semibold text-ink">Pekan {m.minggu_ke}</h2>
                    {evaluasi ? (
                      <span className="text-sm font-medium text-amber-900">{evaluasi}</span>
                    ) : (
                      m.sub_cpmk && <Badge tone="brand">{m.sub_cpmk}</Badge>
                    )}
                    {m.estimasi_waktu?.teks && (
                      <span className="text-[11px] italic text-muted">{m.estimasi_waktu.teks}</span>
                    )}
                  </div>
                  {!evaluasi && m.materi_pustaka && (
                    <p className="mt-0.5 line-clamp-2 text-xs text-muted">{m.materi_pustaka}</p>
                  )}
                </div>
                {rincian.length === 0 ? (
                  <p className="px-5 py-3 text-sm text-muted">Tidak ada rincian untuk pekan ini.</p>
                ) : rincian.some((p) => (p.tahapan?.length ?? 0) > 0) ? (
                  <div className="space-y-4 px-5 py-4">
                    {rincian.map((p) => (
                      <div key={p.pertemuan_ke}>
                        {p.topik && (
                          <p className="mb-2 text-sm font-medium text-ink">
                            {p.topik}
                            {p.durasi_menit ? (
                              <span className="ml-2 text-xs font-normal text-muted">({p.durasi_menit} menit kontak)</span>
                            ) : null}
                          </p>
                        )}
                        <Table bordered>
                          <thead>
                            <tr>
                              <Th className="w-48">Tahap</Th>
                              <Th>Kegiatan</Th>
                              <Th className="w-24 text-right">Durasi</Th>
                            </tr>
                          </thead>
                          <tbody>
                            {(p.tahapan ?? []).map((t, i) => (
                              <tr key={i} className="align-top hover:bg-gray-50">
                                <Td className="font-medium text-ink">{t.tahap ?? "—"}</Td>
                                <Td>{t.kegiatan ?? "—"}</Td>
                                <Td className="text-right tabular-nums">{t.durasi_menit ? `${t.durasi_menit}’` : "—"}</Td>
                              </tr>
                            ))}
                          </tbody>
                        </Table>
                        {(p.penugasan_terstruktur || p.belajar_mandiri) && (
                          <div className="mt-2 space-y-1 text-xs text-muted">
                            {p.penugasan_terstruktur && (
                              <p>
                                <span className="font-medium text-ink">
                                  Penugasan Terstruktur{p.pt_menit ? ` (~${p.pt_menit} menit)` : ""}:
                                </span>{" "}
                                {p.penugasan_terstruktur}
                              </p>
                            )}
                            {p.belajar_mandiri && (
                              <p>
                                <span className="font-medium text-ink">
                                  Belajar Mandiri{p.bm_menit ? ` (~${p.bm_menit} menit)` : ""}:
                                </span>{" "}
                                {p.belajar_mandiri}
                              </p>
                            )}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <Table bordered>
                    <thead>
                      <tr>
                        <Th className="w-16 text-right">Ke</Th>
                        <Th>Topik</Th>
                        <Th>Aktivitas Pembelajaran</Th>
                        <Th>Metode</Th>
                        <Th className="w-28 text-right">Durasi</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {rincian.map((p) => (
                        <tr key={p.pertemuan_ke} className="align-top hover:bg-gray-50">
                          <Td className="text-right font-medium tabular-nums">{p.pertemuan_ke}</Td>
                          <Td className="font-medium text-ink">{p.topik ?? "—"}</Td>
                          <Td>{p.aktivitas ?? "—"}</Td>
                          <Td>{p.metode ?? "—"}</Td>
                          <Td className="text-right tabular-nums">
                            {p.durasi_menit ? `${p.durasi_menit}’` : "—"}
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
