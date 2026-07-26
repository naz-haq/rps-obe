import Link from "next/link";
import { notFound } from "next/navigation";
import {
  apiGet,
  type Single,
  type Kurikulum,
  type BukuKelengkapan,
  type BukuPratinjau,
  type ProdiVmts,
} from "@/lib/api";
import { PageHeader, Card, CardBody, Table, Th, Td, EmptyState } from "@/components/ui";
import { KurikulumTabs } from "../tabs";
import { BukuControls } from "./controls";
import { VmtsManager } from "./vmts-manager";

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

  const vmtsVersions = lengkap
    ? await apiGet<{ data: ProdiVmts[] }>(`/prodi-vmts`, { institusi_id: kurikulum.institusi_id })
        .then((r) => r.data)
        .catch(() => [] as ProdiVmts[])
    : [];

  return (
    <div>
      <PageHeader
        title={kurikulum.nama}
        subtitle={`${kurikulum.kode ? kurikulum.kode + " · " : ""}Tahun ${kurikulum.tahun} · Dokumen Kurikulum (KPT 2024)`}
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
            <h2 className="text-sm font-semibold text-ink">Dokumen Kurikulum belum dapat dibuat</h2>
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
                  Narasi hanya melengkapi bagian prosa akademik (pengantar, landasan, penjelasan CPL/MK, modalitas).
                  Data/tabel tetap deterministik. Bagian kebijakan institusi tetap diisi program studi.
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
                  draf prosa berdasarkan data kurikulum.
                </p>
              ) : (
                <div className="space-y-4">
                  <NarasiBlok judul="Kata Pengantar" teks={naratif.pengantar} />
                  <NarasiBlok judul="Landasan Pengembangan Kurikulum" teks={naratif.landasan} />
                  <NarasiBlok judul="Penjelasan CPL" teks={naratif.cpl} />
                  <NarasiBlok judul="Penjelasan Pembentukan Mata Kuliah" teks={naratif.mata_kuliah} />
                  <NarasiBlok judul="Modalitas Pembelajaran" teks={naratif.modalitas} />
                </div>
              )}
            </CardBody>
          </Card>

          <p className="text-xs text-muted">
            Struktur mengikuti sistematika 12 bagian (I–XII) Panduan KPT 2024. Bagian bertanda{" "}
            <span className="italic">placeholder</span> dilengkapi program studi setelah diunduh.
          </p>

          {/* I. Identitas */}
          <Bab nomor="I" judul="Identitas Program Studi">
            <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
              <Info label="Perguruan Tinggi" nilai={buku.identitas.universitas?.nama} />
              <Info label="Fakultas" nilai={buku.identitas.fakultas?.nama} />
              <Info label="Program Studi" nilai={buku.identitas.prodi?.nama} />
              <Info label="Akreditasi" nilai={buku.identitas.prodi?.akreditasi} />
              <Info label="Jenjang Pendidikan" nilai={buku.identitas.prodi?.jenjang} />
              <Info label="Gelar Lulusan" nilai={buku.identitas.prodi?.gelar} />
              <Info label="Nama Kurikulum" nilai={buku.identitas.kurikulum.nama} />
              <Info label="Tahun" nilai={buku.identitas.kurikulum.tahun} />
              <Info label="Status" nilai={buku.identitas.kurikulum.status} />
              <Info label="Visi" nilai={buku.identitas.vmts?.visi} />
            </dl>
            <p className="mt-2 text-xs text-muted">
              Akreditasi/jenjang/gelar diisi di menu <span className="font-medium">Prodi</span>. Visi & Misi lengkap dikelola
              di Bab IV di bawah.
            </p>
          </Bab>

          {/* II. Evaluasi Kurikulum & Tracer Study */}
          <Bab nomor="II" judul="Evaluasi Kurikulum dan Tracer Study">
            <Placeholder teks="Sajikan mekanisme dan hasil evaluasi kurikulum serta analisis kebutuhan dari tracer study dan pemangku kepentingan." />
          </Bab>

          {/* III. Landasan */}
          <Bab nomor="III" judul="Landasan Perancangan dan Pengembangan Kurikulum">
            {naratif.landasan ? (
              <NarasiBlok teks={naratif.landasan} />
            ) : (
              <Placeholder teks="Uraikan landasan filosofis, sosiologis, psikologis, dan yuridis. Dapat dibantu tombol Generate Narasi (AI)." />
            )}
          </Bab>

          {/* IV. VMTS */}
          <Bab nomor="IV" judul="Visi, Misi, Tujuan, dan Strategi">
            <VmtsManager
              kurikulumId={id}
              currentVmtsId={kurikulum.vmts_id ?? null}
              versions={vmtsVersions}
            />
            {buku.identitas.vmts ? (
              <div className="space-y-3">
                {buku.identitas.vmts.visi && (
                  <div>
                    <h4 className="mb-1 text-xs font-semibold text-brand-700">Visi</h4>
                    <p className="text-sm text-ink">{buku.identitas.vmts.visi}</p>
                  </div>
                )}
                <VmtsList judul="Misi" items={buku.identitas.vmts.misi} />
                <VmtsList judul="Tujuan" items={buku.identitas.vmts.tujuan} />
                <VmtsList judul="Strategi" items={buku.identitas.vmts.strategi} />
                {buku.identitas.universitas?.nilai_institusi && (
                  <div>
                    <h4 className="mb-1 text-xs font-semibold text-brand-700">University Value</h4>
                    <p className="text-sm text-ink">{buku.identitas.universitas.nilai_institusi}</p>
                  </div>
                )}
              </div>
            ) : (
              <Placeholder teks="Pilih atau buat versi VMTS di atas untuk mengisi bagian ini." />
            )}
          </Bab>

          {/* V. CPL */}
          <Bab nomor="V" judul="Capaian Pembelajaran Lulusan (CPL)">
            {naratif.cpl && <NarasiBlok teks={naratif.cpl} />}
            <SubJudul teks={`Profil Lulusan (${buku.profil_lulusan.length})`} />
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
            <SubJudul teks={`Rumusan CPL (${buku.cpl.length})`} />
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
          </Bab>

          {/* VI. Bahan Kajian */}
          <Bab nomor="VI" judul="Penetapan Bahan Kajian">
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
            <SubJudul teks="Keterkaitan CPL × Bahan Kajian" />
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
          </Bab>

          {/* VII. MK & SKS */}
          <Bab nomor="VII" judul="Pembentukan Mata Kuliah dan Penentuan Bobot SKS">
            {naratif.mata_kuliah && <NarasiBlok teks={naratif.mata_kuliah} />}
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
          </Bab>

          {/* VIII. Matrik & Peta */}
          <Bab nomor="VIII" judul="Matrik, Peta Kurikulum, dan Masa Tempuh">
            <SubJudul teks="Matriks Profil Lulusan × CPL" />
            <MatriksGrid
              kolom={cplKolom}
              baris={buku.matriks_pl_cpl.map((r) => ({ label: r.profil, aktif: r.cpl }))}
              labelHead="Profil"
            />
            <SubJudul teks="Matriks CPL × Mata Kuliah" />
            <MatriksGrid
              kolom={cplKolom}
              baris={buku.matriks_mk_cpl.map((r) => ({ label: r.kode_mk, aktif: r.cpl }))}
              labelHead="Mata Kuliah"
            />
          </Bab>

          {/* IX. Modalitas & RPS */}
          <Bab nomor="IX" judul="Modalitas Pembelajaran dan Rencana Pembelajaran Semester (RPS)">
            {naratif.modalitas ? (
              <NarasiBlok teks={naratif.modalitas} />
            ) : (
              <Placeholder teks="Jelaskan modalitas pembelajaran (gaya belajar, Student-Centered Learning, blended learning). Dapat dibantu tombol Generate Narasi (AI)." />
            )}
            <SubJudul teks="Ringkasan RPS per Mata Kuliah" />
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
          </Bab>

          {/* X. MBKM */}
          <Bab nomor="X" judul="Rencana Implementasi Hak Belajar Maksimum 3 Semester di Luar Program Studi">
            <Placeholder teks="Uraikan penempatan BKP MBKM dalam struktur kurikulum dan mekanisme pengakuan kredit." />
          </Bab>

          {/* XI. Manajemen & SPMI */}
          <Bab nomor="XI" judul="Manajemen dan Mekanisme Pelaksanaan Kurikulum">
            <Placeholder teks="Jelaskan rencana pelaksanaan kurikulum dan perangkat Sistem Penjaminan Mutu Internal (SPMI)." />
          </Bab>

          {/* XII. Penerimaan Mahasiswa */}
          <Bab nomor="XII" judul="Tata Cara Penerimaan Mahasiswa pada Berbagai Tahapan Kurikulum">
            <Placeholder teks="Tuliskan tata cara penerimaan mahasiswa pada setiap tahapan kurikulum sesuai kebijakan perguruan tinggi." />
          </Bab>
        </div>
      )}
    </div>
  );
}

function Bab({ nomor, judul, children }: { nomor: string; judul: string; children: React.ReactNode }) {
  return (
    <Card>
      <div className="border-b border-border px-5 py-3.5">
        <h3 className="text-sm font-semibold text-ink">
          <span className="text-brand-700">{nomor}.</span> {judul}
        </h3>
      </div>
      <CardBody>{children}</CardBody>
    </Card>
  );
}

function SubJudul({ teks }: { teks: string }) {
  return <h4 className="mb-1.5 mt-4 text-xs font-semibold text-muted first:mt-0">{teks}</h4>;
}

function VmtsList({ judul, items }: { judul: string; items: string[] }) {
  if (!items || items.length === 0) return null;
  return (
    <div>
      <h4 className="mb-1 text-xs font-semibold text-brand-700">{judul}</h4>
      <ol className="list-inside list-decimal space-y-0.5 text-sm text-ink">
        {items.map((it, i) => (
          <li key={i}>{it}</li>
        ))}
      </ol>
    </div>
  );
}

function Placeholder({ teks }: { teks: string }) {
  return (
    <p className="rounded-md border border-dashed border-border bg-gray-50 px-3 py-2 text-sm italic text-muted">
      [Dilengkapi oleh program studi] {teks}
    </p>
  );
}

function Info({ label, nilai }: { label: string; nilai?: string | null }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      {nilai ? (
        <dd className="text-ink">{nilai}</dd>
      ) : (
        <dd className="text-sm italic text-muted">[dilengkapi prodi]</dd>
      )}
    </div>
  );
}

function NarasiBlok({ judul, teks }: { judul?: string; teks?: string }) {
  if (!teks) return null;
  return (
    <div>
      {judul && <h4 className="mb-1 text-xs font-semibold text-brand-700">{judul}</h4>}
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
