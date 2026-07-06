import Link from "next/link";
import { notFound } from "next/navigation";
import {
  apiGet,
  type Single,
  type Kurikulum,
  type Matriks,
  type Traceability,
  type MatriksProfilLulusan,
  type MatriksBahanKajian,
  type MatriksMkBahanKajian,
} from "@/lib/api";
import { PageHeader, Card, CardBody, Stat, Badge, Table, Th, Td, EmptyState } from "@/components/ui";
import { KurikulumTabs } from "./tabs";
import { ProfilLulusanCplMatrix } from "./profil-lulusan/matriks";
import { CplBahanKajianMatrix } from "./bahan-kajian/matriks";
import { CplMataKuliahMatrix } from "./mata-kuliah/matriks";
import { MkBahanKajianMatrix } from "./mata-kuliah/matriks-bk";

export default async function KurikulumDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  let kurikulum: Kurikulum;
  try {
    const res = await apiGet<Single<Kurikulum>>(`/kurikulum/${id}`);
    kurikulum = res.data;
  } catch {
    notFound();
  }

  const [matriksRes, traceRes, plRes, bkRes, mkBkRes] = await Promise.all([
    apiGet<Single<Matriks>>(`/kurikulum/${id}/matriks`).catch(() => null),
    apiGet<Single<Traceability>>(`/kurikulum/${id}/traceability`).catch(() => null),
    apiGet<Single<MatriksProfilLulusan>>(`/kurikulum/${id}/matriks-profil-lulusan`).catch(() => null),
    apiGet<Single<MatriksBahanKajian>>(`/kurikulum/${id}/matriks-bahan-kajian`).catch(() => null),
    apiGet<Single<MatriksMkBahanKajian>>(`/kurikulum/${id}/matriks-mk-bahan-kajian`).catch(() => null),
  ]);
  const matriks = matriksRes?.data ?? null;
  const trace = traceRes?.data ?? null;
  const plMatriks = plRes?.data ?? null;
  const bkMatriks = bkRes?.data ?? null;
  const mkBkMatriks = mkBkRes?.data ?? null;

  return (
    <div>
      <PageHeader
        title={kurikulum.nama}
        subtitle={`${kurikulum.kode ? kurikulum.kode + " · " : ""}Tahun ${kurikulum.tahun}`}
        actions={
          <Link href="/kurikulum" className="text-sm text-brand-700 hover:underline">
            ← Semua kurikulum
          </Link>
        }
      />

      <KurikulumTabs id={id} active="" />

      <p className="mt-4 text-xs text-muted">
        Ringkasan sekaligus pusat pemetaan: klik sel matriks untuk menautkan/melepas. Tab lain hanya
        untuk mengelola entitas (menambah/menyunting Profil Lulusan, CPL, Bahan Kajian, Mata Kuliah).
      </p>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Stat label="Mata Kuliah" value={matriks?.mata_kuliah.length ?? kurikulum.mata_kuliah_count ?? 0} />
        <Stat label="CPL" value={matriks?.cpl.length ?? kurikulum.cpl_count ?? 0} />
        <Stat label="Tautan MK×CPL" value={matriks?.links.length ?? 0} />
        <Stat
          label="CPL Yatim"
          value={trace?.cpl_yatim.length ?? 0}
          hint={trace && trace.cpl_yatim.length > 0 ? "Perlu ditautkan ke MK" : "Semua CPL terpetakan"}
        />
      </div>

      {/* Matriks Profil Lulusan × CPL (interaktif) */}
      <Card className="mt-6">
        {plMatriks ? (
          <ProfilLulusanCplMatrix
            kurikulumId={kurikulum.id}
            matriks={plMatriks}
            title="Matriks Profil Lulusan × CPL"
            subtitle="Klik sel untuk menautkan CPL yang mendukung profil lulusan."
          />
        ) : (
          <>
            <div className="border-b border-border px-5 py-3.5">
              <h2 className="text-sm font-semibold text-ink">Matriks Profil Lulusan × CPL</h2>
            </div>
            <EmptyState title="Matriks belum tersedia" hint="Tambahkan profil lulusan & CPL terlebih dahulu." />
          </>
        )}
      </Card>

      {/* Matriks CPL × MK */}
      <Card className="mt-6">
        {matriks ? (
          <CplMataKuliahMatrix
            kurikulumId={kurikulum.id}
            matriks={matriks}
            title="Matriks CPL × Mata Kuliah"
            subtitle="Klik sel untuk menautkan CPL ke mata kuliah pengembannya. CPL bertanda ⚠ (yatim) belum diampu mata kuliah mana pun — tautkan minimal ke satu MK."
          />
        ) : (
          <>
            <div className="border-b border-border px-5 py-3.5">
              <h2 className="text-sm font-semibold text-ink">Matriks CPL × Mata Kuliah</h2>
            </div>
            <EmptyState title="Matriks belum tersedia" hint="Tambahkan mata kuliah & CPL terlebih dahulu." />
          </>
        )}
      </Card>

      {/* Matriks CPL × Bahan Kajian (interaktif) */}
      <Card className="mt-6">
        {bkMatriks ? (
          <CplBahanKajianMatrix
            kurikulumId={kurikulum.id}
            matriks={bkMatriks}
            title="Matriks CPL × Bahan Kajian"
            subtitle="Klik sel untuk menautkan bahan kajian ke CPL yang ditopangnya."
          />
        ) : (
          <>
            <div className="border-b border-border px-5 py-3.5">
              <h2 className="text-sm font-semibold text-ink">Matriks CPL × Bahan Kajian</h2>
            </div>
            <EmptyState title="Matriks belum tersedia" hint="Tambahkan bahan kajian & CPL terlebih dahulu." />
          </>
        )}
      </Card>

      {/* Matriks Bahan Kajian × Mata Kuliah (acuan peninjauan struktur) */}
      <Card className="mt-6">
        {mkBkMatriks ? (
          <MkBahanKajianMatrix
            kurikulumId={kurikulum.id}
            matriks={mkBkMatriks}
            title="Matriks Bahan Kajian × Mata Kuliah"
            subtitle="Acuan peninjauan kembali: klik sel untuk menandai bahan kajian yang dibungkus tiap mata kuliah. Bahan kajian bertanda ⚠ (yatim) belum masuk mata kuliah mana pun."
          />
        ) : (
          <>
            <div className="border-b border-border px-5 py-3.5">
              <h2 className="text-sm font-semibold text-ink">Matriks Bahan Kajian × Mata Kuliah</h2>
            </div>
            <EmptyState title="Matriks belum tersedia" hint="Tambahkan mata kuliah & bahan kajian terlebih dahulu." />
          </>
        )}
      </Card>

      {/* Traceability */}
      <Card className="mt-6">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Traceability CPL</h2>
          <p className="text-xs text-muted">Tiap CPL beserta mata kuliah pengembannya.</p>
        </div>
        {!trace || trace.peta.length === 0 ? (
          <EmptyState title="Belum ada CPL" hint="Tambahkan CPL untuk melihat keterlacakan." />
        ) : (
          <Table>
            <thead>
              <tr>
                <Th>CPL</Th>
                <Th>Deskripsi</Th>
                <Th>Mata Kuliah Pengemban</Th>
                <Th className="text-right">Status</Th>
              </tr>
            </thead>
            <tbody>
              {trace.peta.map((p) => (
                <tr key={p.cpl_id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{p.kode}</Td>
                  <Td className="max-w-md text-muted">{p.deskripsi}</Td>
                  <Td>
                    {p.mata_kuliah.length === 0 ? (
                      <span className="text-xs text-muted">—</span>
                    ) : (
                      <div className="flex flex-wrap gap-1">
                        {p.mata_kuliah.map((m) => (
                          <Badge key={m.kode_mk} tone="neutral">{m.kode_mk}</Badge>
                        ))}
                      </div>
                    )}
                  </Td>
                  <Td className="text-right">
                    {p.yatim ? <Badge tone="danger">Yatim</Badge> : <Badge tone="ok">Terpetakan</Badge>}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      <Card className="mt-6">
        <CardBody className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-muted">Siapkan RPS untuk mata kuliah pada kurikulum ini.</p>
          <Link href="/generator" className="text-sm font-medium text-brand-700 hover:underline">
            Buka Generator RPS →
          </Link>
        </CardBody>
      </Card>
    </div>
  );
}
