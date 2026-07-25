import Link from "next/link";
import { notFound } from "next/navigation";
import {
  apiGet,
  type Single,
  type Kurikulum,
  type BukuKelengkapan,
  type BukuPratinjau,
} from "@/lib/api";
import { PageHeader, Card, CardBody, Table, Th, Td, Badge, EmptyState } from "@/components/ui";
import { KurikulumTabs } from "../tabs";
import { BukuControls } from "./controls";

export default async function BukuKurikulumPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  let kurikulum: Kurikulum;
  try {
    kurikulum = (await apiGet<Single<Kurikulum>>(`/kurikulum/${id}`)).data;
  } catch {
    notFound();
  }

  const kelengkapan = await apiGet<Single<BukuKelengkapan>>(`/kurikulum/${id}/buku/kelengkapan`)
    .then((r) => r.data)
    .catch(() => null);

  const lengkap = kelengkapan?.lengkap ?? false;

  const pratinjau = lengkap
    ? await apiGet<BukuPratinjau>(`/kurikulum/${id}/buku/pratinjau`).catch(() => null)
    : null;

  const buku = pratinjau?.data ?? null;
  const naratif = pratinjau?.naratif ?? {};
  const cplKolom = buku?.cpl.map((c) => c.kode) ?? [];

  return (
    <div>
      <PageHeader
        title={kurikulum.nama}
        subtitle={`${kurikulum.kode ? kurikulum.kode + " · " : ""}Tahun ${kurikulum.tahun} · Buku Kurikulum`}
        actions={
          <Link href={`/kurikulum/${id}`} className="text-sm text-brand-700 hover:underline">
            ← Ringkasan
          </Link>
        }
      />

      <KurikulumTabs id={id} active="buku" />

      {!lengkap ? (
        <Card>
          <div className="border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Buku Kurikulum belum dapat dibuat</h2>
          </div>
          <CardBody>
            {kelengkapan ? (
              <div className="text-sm">
                <p className="text-ink">
                  {kelengkapan.mk_ada_rps} dari {kelengkapan.total_mk} mata kuliah sudah memiliki RPS. Lengkapi RPS mata
                  kuliah berikut lebih dulu:
                </p>
                <ul className="mt-2 flex flex-wrap gap-2">
                  {kelengkapan.mk_belum_rps.map((m) => (
                    <li
                      key={m.kode_mk}
                      className="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800"
                    >
                      {m.kode_mk} — {m.nama}
                      {m.semester ? ` (Sem ${m.semester})` : ""}
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <p className="text-sm text-muted">Status kelengkapan belum tersedia.</p>
            )}
          </CardBody>
        </Card>
      ) : !buku ? (
        <EmptyState title="Pratinjau tidak tersedia" hint="Coba muat ulang halaman." />
      ) : (
        <div className="space-y-6">
          {/* Panel narasi AI — generate/regenerate + tinjau */}
          <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3.5">
              <div>
                <h2 className="text-sm font-semibold text-ink">Narasi (AI) — tinjau sebelum unduh</h2>
                <p className="mt-0.5 text-xs text-muted">
                  Narasi hanya melengkapi prosa; seluruh data/tabel di bawah tetap deterministik dari kurikulum.
                </p>
              </div>
              <BukuControls
                kurikulumId={id}
                sudahAdaNaratif={Object.keys(naratif).length > 0}
                naratifPada={pratinjau?.naratif_pada ?? null}
              />
            </div>
            <CardBody>
              {Object.keys(naratif).length === 0 ? (
                <p className="text-sm text-muted">
                  Belum ada narasi. Klik <span className="font-medium text-ink">Generate Narasi (AI)</span> untuk menyusun
                  kata pengantar dan penjelasan naratif berdasarkan data kurikulum.
                </p>
              ) : (
                <div className="space-y-4">
                  <NarasiBlok judul="Kata Pengantar" teks={naratif.pengantar} />
                  <NarasiBlok judul="Profil Lulusan" teks={naratif.profil_lulusan} />
                  <NarasiBlok judul="Capaian Pembelajaran (CPL)" teks={naratif.cpl} />
                  <NarasiBlok judul="Struktur Mata Kuliah" teks={naratif.mata_kuliah} />
                </div>
              )}
            </CardBody>
          </Card>

          {/* Identitas */}
          <Seksi judul="Identitas">
            <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
              <Info label="Program Studi" nilai={buku.identitas.prodi?.nama} />
              <Info label="Fakultas" nilai={buku.identitas.fakultas?.nama} />
              <Info label="Universitas" nilai={buku.identitas.universitas?.nama} />
              <Info label="Kurikulum" nilai={buku.identitas.kurikulum.nama} />
              <Info label="Tahun" nilai={buku.identitas.kurikulum.tahun} />
              <Info label="Status" nilai={buku.identitas.kurikulum.status} />
            </dl>
          </Seksi>

          {/* Profil Lulusan */}
          <Seksi judul={`Profil Lulusan (${buku.profil_lulusan.length})`}>
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Deskripsi</Th>
                </tr>
              </thead>
              <tbody>
                {buku.profil_lulusan.map((p) => (
                  <tr key={p.kode}>
                    <Td className="font-medium">{p.kode}</Td>
                    <Td>{p.deskripsi}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </Seksi>

          {/* CPL */}
          <Seksi judul={`Capaian Pembelajaran Lulusan (${buku.cpl.length})`}>
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Aspek</Th>
                  <Th>KKNI</Th>
                  <Th>Deskripsi</Th>
                </tr>
              </thead>
              <tbody>
                {buku.cpl.map((c) => (
                  <tr key={c.kode}>
                    <Td className="font-medium">{c.kode}</Td>
                    <Td>{c.aspek ?? "-"}</Td>
                    <Td>{c.level_kkni ?? "-"}</Td>
                    <Td>{c.deskripsi}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </Seksi>

          {/* Matriks PL × CPL */}
          <Seksi judul="Matriks Profil Lulusan × CPL">
            <MatriksGrid
              kolom={cplKolom}
              baris={buku.matriks_pl_cpl.map((r) => ({ label: r.profil, aktif: r.cpl }))}
              labelHead="Profil"
            />
          </Seksi>

          {/* Bahan Kajian + CPL×BK */}
          <Seksi judul={`Bahan Kajian (${buku.bahan_kajian.length})`}>
            <Table>
              <thead>
                <tr>
                  <Th>Bahan Kajian</Th>
                  <Th>Deskripsi</Th>
                </tr>
              </thead>
              <tbody>
                {buku.bahan_kajian.map((b) => (
                  <tr key={b.nama}>
                    <Td className="font-medium">{b.nama}</Td>
                    <Td>{b.deskripsi ?? "-"}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
            <div className="mt-4">
              <h4 className="mb-2 text-xs font-semibold text-muted">Keterkaitan CPL × Bahan Kajian</h4>
              <Table>
                <thead>
                  <tr>
                    <Th>CPL</Th>
                    <Th>Bahan Kajian Penopang</Th>
                  </tr>
                </thead>
                <tbody>
                  {buku.matriks_cpl_bk.map((r) => (
                    <tr key={r.cpl}>
                      <Td className="font-medium">{r.cpl}</Td>
                      <Td>{r.bahan_kajian.length ? r.bahan_kajian.join("; ") : "—"}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </div>
          </Seksi>

          {/* Struktur MK per semester */}
          <Seksi judul="Struktur Mata Kuliah">
            <div className="space-y-4">
              {buku.mata_kuliah.map((grup) => (
                <div key={grup.semester ?? "none"}>
                  <h4 className="mb-1.5 text-xs font-semibold text-brand-700">
                    {grup.semester !== null ? `Semester ${grup.semester}` : "Tanpa Semester"}
                  </h4>
                  <Table>
                    <thead>
                      <tr>
                        <Th>Kode</Th>
                        <Th>Nama Mata Kuliah</Th>
                        <Th>SKS</Th>
                        <Th>Sifat</Th>
                        <Th>Jenis</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {grup.mata_kuliah.map((mk) => (
                        <tr key={mk.kode_mk}>
                          <Td className="font-medium">{mk.kode_mk}</Td>
                          <Td>{mk.nama}</Td>
                          <Td>{mk.sks}</Td>
                          <Td>{mk.sifat ?? "-"}</Td>
                          <Td>{mk.jenis_mk ?? "-"}</Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                </div>
              ))}
            </div>
          </Seksi>

          {/* Matriks CPL × MK */}
          <Seksi judul="Matriks CPL × Mata Kuliah">
            <MatriksGrid
              kolom={cplKolom}
              baris={buku.matriks_mk_cpl.map((r) => ({ label: `${r.kode_mk}`, aktif: r.cpl }))}
              labelHead="Mata Kuliah"
            />
          </Seksi>

          {/* Ringkasan RPS */}
          <Seksi judul="Ringkasan RPS per Mata Kuliah">
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Mata Kuliah</Th>
                  <Th>Versi</Th>
                  <Th>CPMK</Th>
                  <Th>Sub-CPMK</Th>
                  <Th>Pekan</Th>
                  <Th>Komponen</Th>
                </tr>
              </thead>
              <tbody>
                {buku.rps_ringkas.map((r) => (
                  <tr key={r.kode_mk}>
                    <Td className="font-medium">{r.kode_mk}</Td>
                    <Td>{r.nama}</Td>
                    <Td>{r.versi}</Td>
                    <Td>{r.jumlah_cpmk}</Td>
                    <Td>{r.jumlah_sub_cpmk}</Td>
                    <Td>{r.jumlah_minggu}</Td>
                    <Td>{r.jumlah_komponen}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </Seksi>
        </div>
      )}
    </div>
  );
}

function Seksi({ judul, children }: { judul: string; children: React.ReactNode }) {
  return (
    <Card>
      <div className="border-b border-border px-5 py-3.5">
        <h3 className="text-sm font-semibold text-ink">{judul}</h3>
      </div>
      <CardBody>{children}</CardBody>
    </Card>
  );
}

function Info({ label, nilai }: { label: string; nilai?: string | null }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="text-ink">{nilai || "—"}</dd>
    </div>
  );
}

function NarasiBlok({ judul, teks }: { judul: string; teks?: string }) {
  if (!teks) return null;
  return (
    <div>
      <h4 className="mb-1 text-xs font-semibold text-brand-700">{judul}</h4>
      {teks.split(/\n{2,}/).map((par, i) => (
        <p key={i} className="mb-2 whitespace-pre-line text-sm leading-relaxed text-ink">
          {par}
        </p>
      ))}
    </div>
  );
}

function MatriksGrid({
  kolom,
  baris,
  labelHead,
}: {
  kolom: string[];
  baris: { label: string; aktif: string[] }[];
  labelHead: string;
}) {
  if (kolom.length === 0 || baris.length === 0) {
    return <p className="text-sm text-muted">Belum ada data pemetaan.</p>;
  }
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full border-collapse text-xs">
        <thead>
          <tr>
            <th className="border border-border bg-gray-50 px-2 py-1 text-left font-semibold text-ink">{labelHead}</th>
            {kolom.map((k) => (
              <th key={k} className="border border-border bg-gray-50 px-2 py-1 text-center font-semibold text-ink">
                {k}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {baris.map((row) => (
            <tr key={row.label}>
              <td className="border border-border px-2 py-1 font-medium text-ink">{row.label}</td>
              {kolom.map((k) => (
                <td key={k} className="border border-border px-2 py-1 text-center">
                  {row.aktif.includes(k) ? <span className="font-bold text-brand-700">✓</span> : ""}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
