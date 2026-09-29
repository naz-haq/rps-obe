import Link from "next/link";
import { notFound } from "next/navigation";
import { apiGet, BACKEND_PROXY, type Single, type RpsDetail, type RpsTraceability, type RpsApprovalLog } from "@/lib/api";
import { rpsStatusLabel, rpsStatusTone, deteksiUjian } from "@/lib/rps-status";
import { PageHeader, Card, CardBody, Stat, Badge, Table, Th, Td, EmptyState, BulletCell } from "@/components/ui";
import { ApprovalActions } from "./approval";
import { KelengkapanButton } from "./kelengkapan";
import { ReopenButton } from "../../generator/reopen-button";

const AKSI_LABEL: Record<string, string> = {
  ajukan: "Diajukan untuk tinjauan",
  setujui: "Disetujui",
  revisi: "Diminta revisi",
  tarik: "Pengajuan ditarik",
  buka_draf: "Dikembalikan ke draf generator",
};

export default async function RpsDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  let detail: RpsDetail;
  try {
    const res = await apiGet<Single<RpsDetail>>(`/rps-versions/${id}`);
    detail = res.data;
  } catch {
    notFound();
  }

  const trace = await apiGet<Single<RpsTraceability>>(`/rps-versions/${id}/traceability`)
    .then((r) => r.data)
    .catch(() => null);

  const riwayat = await apiGet<{ data: RpsApprovalLog[] }>(`/rps-versions/${id}/riwayat-persetujuan`)
    .then((r) => r.data)
    .catch(() => [] as RpsApprovalLog[]);

  const { rps, minggu, komponen, konteks, obe } = detail;
  const totalBobot =
    Math.round(komponen.reduce((a, k) => a + Number(k.bobot_persen ?? 0), 0) * 100) / 100;

  return (
    <div>
      <PageHeader
        title={rps.nama_mk ?? rps.kode_mk}
        subtitle={`${rps.kode_mk} · v${rps.versi}`}
        actions={
          <div className="flex items-center gap-3">
            {!rps.editing_in_generator && <ApprovalActions id={rps.id} status={rps.status} />}
            {rps.can_reopen && rps.generate_session_id && <ReopenButton sessionId={rps.generate_session_id} navigate />}
            {rps.editing_in_generator && rps.generate_session_id && <Link href={`/generator/${rps.generate_session_id}`} className="text-sm text-brand-700 underline">Lanjutkan penyuntingan draf</Link>}
            <a
              href={`${BACKEND_PROXY}/rps-versions/${id}/cetak`}
              target="_blank"
              rel="noopener noreferrer"
              className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"
            >
              Cetak / Unduh PDF
            </a>
            <a
              href={`${BACKEND_PROXY}/rps-versions/${id}/docx`}
              className="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-ink hover:bg-gray-50"
            >
              Unduh DOCX
            </a>
            <span className="text-xs text-muted">
              Format KPT 2024:{" "}
              <a href={`${BACKEND_PROXY}/rps-versions/${id}/cetak?format=kpt`} target="_blank" rel="noopener noreferrer" className="text-brand-700 hover:underline">PDF</a>
              {" · "}
              <a href={`${BACKEND_PROXY}/rps-versions/${id}/docx?format=kpt`} className="text-brand-700 hover:underline">DOCX</a>
            </span>
            <Link href="/rps" className="text-sm text-brand-700 hover:underline">
              ← Semua RPS
            </Link>
          </div>
        }
      />

      {rps.status === "revisi" && rps.catatan_review && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <span className="font-semibold">Catatan revisi dari peninjau:</span> {rps.catatan_review}
        </div>
      )}

      {rps.editing_in_generator && <p role="status" className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Dokumen ini menampilkan commit terakhir. Draf sedang disunting; commit ulang diperlukan sebelum pengajuan.</p>}

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Stat label="Minggu" value={minggu.length} />
        <Stat label="Komponen Nilai" value={komponen.length} hint={`Total ${totalBobot}%`} />
        <Stat label="CPL Diampu" value={trace?.cpl_diampu.length ?? 0} />
        <Stat label="Status" value={<Badge tone={rpsStatusTone(rps.status)}>{rpsStatusLabel(rps.status)}</Badge>} />
      </div>

      {obe && (
        <Card className="mt-6">
          <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Kelengkapan Dokumen</h2>
            {rps.final ? (
              <span className="text-xs text-muted">Terkunci (RPS final)</span>
            ) : (
              <KelengkapanButton rps={rps} obe={obe} />
            )}
          </div>
          <CardBody className="grid gap-6 lg:grid-cols-3">
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
              {([
                ["Kode/No. Dokumen", obe.kop.kode_dokumen],
                ["Tanggal Penyusunan", obe.kop.tanggal],
                ["Revisi", String(obe.kop.revisi)],
                ["Berlaku Mulai", obe.kop.berlaku_mulai],
                ["Tahun Akademik", obe.kop.tahun_akademik],
                ["Status Dokumen", obe.kop.status],
                ["Jenjang", obe.identitas.jenjang],
                ["Bidang Keahlian", obe.identitas.bidang_keahlian],
                ["Bentuk Pembelajaran", obe.identitas.bentuk_pembelajaran],
              ] as [string, string | null][]).map(([l, v]) => (
                <div key={l} className="contents">
                  <dt className="text-muted">{l}</dt>
                  <dd className={v ? "text-ink" : "text-amber-700"}>{v || "Belum diisi"}</dd>
                </div>
              ))}
            </dl>

            <div className="space-y-4 text-sm">
              <div>
                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Otorisasi</h3>
                <ul className="space-y-1">
                  {obe.otorisasi.map((o) => (
                    <li key={o.jabatan}>
                      <span className="text-muted">{o.jabatan}:</span>{" "}
                      {o.nama ? (
                        <span className="font-medium text-ink">{o.nama}{o.nidn ? ` · ${o.nidn}` : ""}</span>
                      ) : (
                        <span className="text-amber-700">Belum diisi</span>
                      )}
                      {o.ttd && (
                        <span className="ml-1.5 text-xs text-emerald-700" title={`Kode verifikasi ${o.ttd.kode}`}>
                          ✓ TTD elektronik {o.ttd.waktu}
                        </span>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Kontribusi terhadap CPL</h3>
                {obe.cpl.length === 0 ? (
                  <p className="text-muted">—</p>
                ) : (
                  <div className="flex flex-wrap gap-1.5">
                    {obe.cpl.map((c) => (
                      <Badge key={c.kode} tone={c.kontribusi === "Utama" ? "brand" : "neutral"}>
                        {c.kode} · {c.kontribusi}
                      </Badge>
                    ))}
                  </div>
                )}
              </div>
            </div>

            <div className="space-y-4 text-sm">
              <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
                {([
                  ["LMS", obe.media.lms],
                  [
                    "Proporsi",
                    obe.media.proporsi_luring != null ? `${obe.media.proporsi_luring}% luring · ${obe.media.proporsi_daring}% daring` : null,
                  ],
                  ["Media", obe.media.media_pembelajaran],
                  ["Pengalaman Utama", obe.media.pengalaman_belajar_utama],
                ] as [string, string | null][]).map(([l, v]) => (
                  <div key={l} className="contents">
                    <dt className="text-muted">{l}</dt>
                    <dd className={v ? "text-ink" : "text-amber-700"}>{v || "Belum diisi"}</dd>
                  </div>
                ))}
              </dl>
              <div>
                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Lampiran Instrumen</h3>
                <ul className="space-y-0.5">
                  {obe.lampiran.map((l) => (
                    <li key={l.key} className={l.ada ? "text-ink" : "text-muted"}>
                      {l.ada ? "☑" : "☐"} {l.label}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Konteks MK: Bahan Kajian, Pustaka, Pengampu, Prasyarat, Matriks korelasi */}
      {konteks && (
        <Card className="mt-6">
          <div className="border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Konteks Mata Kuliah</h2>
          </div>
          <CardBody className="grid gap-6 md:grid-cols-2">
            <div>
              <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Bahan Kajian</h3>
              {konteks.bahan_kajian.length === 0 ? (
                <p className="text-sm text-muted">Belum ada bahan kajian tertaut ke MK.</p>
              ) : (
                <ol className="list-decimal space-y-1 pl-5 text-sm">
                  {konteks.bahan_kajian.map((bk, i) => (
                    <li key={i}>
                      <span className="font-medium text-ink">{bk.nama}</span>
                      {bk.deskripsi && <span className="text-muted"> — {bk.deskripsi}</span>}
                      {bk.keterampilan.length > 0 && (
                        <ul className="mt-1 list-disc space-y-0.5 pl-5 text-xs text-muted">
                          {bk.keterampilan.map((k, j) => <li key={j}>{k}</li>)}
                        </ul>
                      )}
                    </li>
                  ))}
                </ol>
              )}
            </div>

            <div className="space-y-4">
              <div>
                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Referensi</h3>
                {(obe?.referensi.length ?? 0) === 0 ? (
                  <p className="text-sm text-muted">—</p>
                ) : (
                  <ul className="space-y-1 text-sm">
                    {obe!.referensi.map((r, i) => (
                      <li key={i}>
                        <span className="text-xs font-medium text-muted">{r.jenis}:</span> {r.sitasi}
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>

            <div>
              <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Dosen Pengampu</h3>
              {konteks.pengampu.length === 0 ? (
                <p className="text-sm text-muted">Belum ada dosen pengampu.</p>
              ) : (
                <ul className="space-y-1 text-sm">
                  {konteks.pengampu.map((d, i) => (
                    <li key={i}>
                      <span className="font-medium text-ink">{d.nama}</span>
                      <span className="text-muted"> · {d.peran}</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <div>
              <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Matakuliah Syarat</h3>
              {konteks.prasyarat ? (
                <p className="text-sm">
                  <span className="font-medium text-ink">{konteks.prasyarat.kode}</span>
                  {konteks.prasyarat.nama && <span className="text-muted"> — {konteks.prasyarat.nama}</span>}
                </p>
              ) : (
                <p className="text-sm text-muted">Tidak ada prasyarat.</p>
              )}
            </div>
          </CardBody>

          {konteks.matriks_korelasi.baris.length > 0 && (
            <div className="border-t border-border">
              <div className="px-5 py-3">
                <h3 className="text-sm font-semibold text-ink">Matriks Korelasi Sub-CPMK × CPL</h3>
                <p className="text-xs text-muted">
                  Bobot % kontribusi Sub-CPMK ke tiap CPL (turunan bobot CPMK × CPL) serta kontribusi Sub-CPMK ke MK berdasarkan jumlah minggu pertemuan (total {konteks.matriks_korelasi.total_minggu} minggu aktif).
                </p>
              </div>
              <Table bordered>
                <thead>
                  <tr>
                    <Th>Sub-CPMK</Th>
                    {konteks.matriks_korelasi.cpl.map((c) => (
                      <Th key={c.id} className="text-center">{c.kode} (%)</Th>
                    ))}
                    <Th className="text-center">Bobot Penilaian (%)</Th>
                  </tr>
                </thead>
                <tbody>
                  {konteks.matriks_korelasi.baris.map((b, i) => (
                    <tr key={i} className="hover:bg-gray-50">
                      <Td className="font-medium text-ink">{b.sub_cpmk}</Td>
                      {konteks.matriks_korelasi.cpl.map((c) => (
                        <Td key={c.id} className="text-center tabular-nums text-muted">
                          {b.bobot_per_cpl[c.kode] != null ? b.bobot_per_cpl[c.kode] : ""}
                        </Td>
                      ))}
                      <Td className="text-center tabular-nums">{b.bobot_nilai ?? ""}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
              {konteks.matriks_korelasi.cpmk_kontribusi.length > 0 && (
                <div className="border-t border-border bg-gray-50 px-5 py-3 text-xs">
                  <span className="font-semibold text-ink">Rekap kontribusi per CPMK</span>
                  <span className="text-muted"> (berdasarkan jumlah minggu pertemuan):</span>
                  <ul className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1">
                    {konteks.matriks_korelasi.cpmk_kontribusi.map((c) => (
                      <li key={c.cpmk} className="tabular-nums">
                        <span className="font-medium text-ink">{c.cpmk}</span>
                        <span className="text-muted"> — {c.jumlah_minggu} mg ({c.kontribusi_persen}%)</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}
        </Card>
      )}

      {/* Rencana mingguan */}
      <Card className="mt-6">
        <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Rencana Pembelajaran Mingguan</h2>
          {minggu.length > 0 && (
            <Link
              href={`/rps/${rps.id}/pertemuan`}
              className="shrink-0 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-gray-50"
            >
              Rincian Pertemuan →
            </Link>
          )}
        </div>
        {minggu.length === 0 ? (
          <EmptyState title="Belum ada data mingguan" />
        ) : (
          <Table bordered>
            <thead>
              <tr>
                <Th className="text-right">Mg</Th>
                <Th>Sub-CPMK</Th>
                <Th>Indikator</Th>
                <Th>Kriteria &amp; Bentuk Penilaian</Th>
                <Th>Bentuk Pembelajaran — Luring</Th>
                <Th>Bentuk Pembelajaran — Daring</Th>
                <Th>Materi Pembelajaran [Pustaka]</Th>
                <Th>Media/Sumber</Th>
                <Th>Bukti/Produk</Th>
                <Th className="text-right">Bobot (%)</Th>
              </tr>
            </thead>
            <tbody>
              {minggu.map((m) => {
                const ujian = deteksiUjian(m.materi_pustaka);
                const isUts = ujian === "uts";
                if (ujian) {
                  return (
                    <tr key={m.minggu_ke} className="bg-amber-50">
                      <Td className="text-right font-medium tabular-nums">{m.minggu_ke}</Td>
                      <Td colSpan={9} className="text-center font-semibold text-amber-900">
                        {isUts ? "Evaluasi Tengah Semester (UTS)" : "Evaluasi Akhir Semester (UAS)"}
                        {m.indikator ? ` — ${m.indikator}` : ""}
                      </Td>
                    </tr>
                  );
                }
                return (
                <tr key={m.minggu_ke} className="align-top hover:bg-gray-50">
                  <Td className="text-right font-medium tabular-nums">{m.minggu_ke}</Td>
                  <Td>
                    {m.sub_cpmk ? (
                      <div className="space-y-1">
                        <Badge tone="brand">{m.sub_cpmk}</Badge>
                        {m.sub_cpmk_bloom && <span className="ml-1 text-[10px] text-muted">{m.sub_cpmk_bloom}</span>}
                        {m.sub_cpmk_deskripsi && <p className="text-xs text-muted">{m.sub_cpmk_deskripsi}</p>}
                        {m.cpmk && (
                          <p className="text-xs text-muted" title={m.cpmk_deskripsi ?? undefined}>
                            {m.cpmk}
                          </p>
                        )}
                        {(m.sub_cpmk_lain ?? []).map((s) => (
                          <p key={s.kode} className="text-xs text-muted">
                            + <span className="font-medium text-ink">{s.kode}</span>
                            {s.deskripsi ? ` — ${s.deskripsi}` : ""}
                          </p>
                        ))}
                      </div>
                    ) : (
                      "—"
                    )}
                  </Td>
                  <Td className="max-w-[14rem] text-muted"><BulletCell value={m.indikator} /></Td>
                  <Td className="max-w-[16rem] whitespace-pre-line text-muted">{m.kriteria_penilaian ?? "—"}</Td>
                  <Td className="max-w-[14rem] text-muted">
                    {m.bentuk_luring ? <div>{m.bentuk_luring}</div> : <span>—</span>}
                    {m.metode_pembelajaran && <div className="text-[11px] text-muted">Metode: {m.metode_pembelajaran}</div>}
                    {m.estimasi_waktu?.teks && (
                      <div className="text-[11px] italic text-muted">{m.estimasi_waktu.teks}</div>
                    )}
                  </Td>
                  <Td className="max-w-[14rem] text-muted">
                    {m.bentuk_daring ? <div>{m.bentuk_daring}</div> : <span>—</span>}
                    {m.pengalaman_belajar && <div className="text-[11px] text-muted">Penugasan: {m.pengalaman_belajar}</div>}
                  </Td>
                  <Td className="max-w-[16rem] text-muted">{m.materi_pustaka ?? "—"}</Td>
                  <Td className="max-w-[12rem] text-muted">{m.media_sumber || "—"}</Td>
                  <Td className="max-w-[12rem] text-muted">{m.bukti_produk || "—"}</Td>
                  <Td className="text-right tabular-nums">
                    {m.bobot_penilaian != null ? `${m.bobot_penilaian}%` : "—"}
                  </Td>
                </tr>
                );
              })}
            </tbody>
          </Table>
        )}
      </Card>

      {/* Komponen penilaian */}
      <Card className="mt-6">
        <div className="flex items-center justify-between border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Komponen Penilaian</h2>
          <Badge tone={totalBobot === 100 ? "ok" : "warn"}>Total {totalBobot}%</Badge>
        </div>
        {komponen.length === 0 ? (
          <EmptyState title="Belum ada komponen penilaian" />
        ) : (
          <Table bordered>
            <thead>
              <tr>
                <Th>Nama</Th>
                <Th>Jenis</Th>
                <Th>Instrumen</Th>
                <Th>Sub-CPMK</Th>
                <Th className="text-right">Minggu</Th>
                <Th className="text-right">Bobot</Th>
              </tr>
            </thead>
            <tbody>
              {komponen.map((k, i) => (
                <tr key={i} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{k.nama}</Td>
                  <Td className="text-muted">{k.jenis ?? "—"}</Td>
                  <Td className="max-w-[14rem] text-muted">{k.instrumen ?? "—"}</Td>
                  <Td>
                    {k.sub_cpmk ? (
                      <div className="space-y-1">
                        <Badge tone="neutral">{k.sub_cpmk}</Badge>
                        {k.sub_cpmk_deskripsi && <p className="text-xs text-muted">{k.sub_cpmk_deskripsi}</p>}
                        {k.cpmk && (
                          <p className="text-xs text-muted" title={k.cpmk_deskripsi ?? undefined}>
                            {k.cpmk}
                          </p>
                        )}
                      </div>
                    ) : (
                      "—"
                    )}
                  </Td>
                  <Td className="text-right tabular-nums">{k.minggu_ke ?? "—"}</Td>
                  <Td className="text-right tabular-nums font-medium">{k.bobot_persen ?? 0}%</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      {/* Rubrik penilaian */}
      {komponen.some((k) => k.rubrik && k.rubrik.kriteria.length > 0) && (
        <Card className="mt-6">
          <div className="border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Rubrik Penilaian</h2>
            <p className="text-xs text-muted">Rubrik analitik per komponen: kriteria, bobot, dan deskriptor mutu tiap level.</p>
          </div>
          <CardBody className="space-y-6">
            {komponen
              .filter((k) => k.rubrik && k.rubrik.kriteria.length > 0)
              .map((k, i) => {
                const r = k.rubrik!;
                const levels = r.jumlah_level_skala || (r.label_skala?.length ?? 4);
                const labels = Array.from({ length: levels }, (_, idx) => r.label_skala?.[idx] ?? `Level ${idx + 1}`);
                return (
                  <div key={i}>
                    <div className="mb-2 flex flex-wrap items-center gap-2">
                      <span className="text-sm font-medium text-ink">{k.nama}</span>
                      <Badge tone="neutral">rubrik {r.jenis}</Badge>
                    </div>
                    <Table bordered>
                      <thead>
                        <tr>
                          <Th>Kriteria</Th>
                          <Th className="text-right">Bobot</Th>
                          {labels.map((l, idx) => (
                            <Th key={idx}>{l}</Th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {r.kriteria.map((kr, ki) => (
                          <tr key={ki} className="align-top hover:bg-gray-50">
                            <Td className="font-medium text-ink">{kr.kriteria}</Td>
                            <Td className="text-right tabular-nums">{kr.bobot != null ? `${kr.bobot}%` : "—"}</Td>
                            {labels.map((_, idx) => (
                              <Td key={idx} className="max-w-[12rem] text-muted">{kr.deskriptor?.[idx] ?? "—"}</Td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  </div>
                );
              })}
          </CardBody>
        </Card>
      )}

      {/* Traceability */}
      <Card className="mt-6">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Traceability OBE</h2>
          <p className="text-xs text-muted">Rantai Sub-CPMK → CPMK → CPL beserta minggu pelaksanaan.</p>
        </div>
        {!trace || trace.rantai.length === 0 ? (
          <EmptyState title="Rantai keterlacakan belum tersedia" />
        ) : (
          <CardBody className="space-y-3">
            <div className="flex flex-wrap gap-1.5">
              <span className="text-xs font-medium text-muted">CPL diampu:</span>
              {trace.cpl_diampu.map((c) => (
                <Badge key={c} tone="ok">{c}</Badge>
              ))}
            </div>
            <ul className="space-y-2">
              {trace.rantai.map((r) => (
                <li key={r.sub_cpmk} className="rounded-lg border border-border bg-gray-50/50 px-3 py-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge tone="brand">{r.sub_cpmk}</Badge>
                    {r.cpmk && <Badge tone="neutral">← {r.cpmk}</Badge>}
                    {r.cpl.map((c) => (
                      <Badge key={c} tone="ok">{c}</Badge>
                    ))}
                    <span className="text-xs text-muted">Minggu {r.minggu.join(", ")}</span>
                  </div>
                  {r.deskripsi && <p className="mt-1 text-sm text-ink">{r.deskripsi}</p>}
                </li>
              ))}
            </ul>
          </CardBody>
        )}
      </Card>

      {/* Riwayat persetujuan (audit trail) */}
      <Card className="mt-6">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Riwayat Persetujuan</h2>
          <p className="text-xs text-muted">Jejak transisi status: siapa, kapan, dari status apa ke apa, dan catatannya.</p>
        </div>
        {riwayat.length === 0 ? (
          <EmptyState title="Belum ada aktivitas persetujuan" hint="Ajukan RPS untuk memulai alur tinjauan." />
        ) : (
          <CardBody>
            <ul className="space-y-3">
              {riwayat.map((log) => (
                <li key={log.id} className="flex gap-3">
                  <div className="mt-1.5 grid h-2 w-2 shrink-0 place-items-center rounded-full bg-brand-500" />
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="text-sm font-medium text-ink">{AKSI_LABEL[log.aksi] ?? log.aksi}</span>
                      <Badge tone={rpsStatusTone(log.dari_status ?? "")}>{rpsStatusLabel(log.dari_status ?? "—")}</Badge>
                      <span className="text-muted">→</span>
                      <Badge tone={rpsStatusTone(log.ke_status)}>{rpsStatusLabel(log.ke_status)}</Badge>
                      {log.actor_nama && <span className="text-xs text-muted">oleh {log.actor_nama}</span>}
                      <span className="ml-auto text-xs text-gray-400">
                        {new Date(log.created_at).toLocaleString("id-ID")}
                      </span>
                    </div>
                    {log.catatan && <p className="mt-0.5 text-sm text-muted">{log.catatan}</p>}
                  </div>
                </li>
              ))}
            </ul>
          </CardBody>
        )}
      </Card>
    </div>
  );
}
