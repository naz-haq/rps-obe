import Link from "next/link";
import { notFound } from "next/navigation";
import { apiGet, type Single, type ChecklistDetail, type BadanRujukan, type Paginated, type ButirKategori } from "@/lib/api";
import { PageHeader, Card, Stat, Badge, Table, Th, Td, EmptyState } from "@/components/ui";
import { CreateButirButton, EditButirButton, DeleteButirButton, StatusControl } from "../forms";

const DEFAULT_INSTITUSI = 1;

const KATEGORI_LABEL: Record<ButirKategori, string> = {
  profil_lulusan: "Profil Lulusan",
  cpl: "CPL",
  bahan_kajian: "Bahan Kajian",
  kriteria_akreditasi: "Kriteria Akreditasi",
  struktur: "Struktur",
  aturan: "Aturan",
};

export default async function ChecklistDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  let detail: ChecklistDetail;
  try {
    const res = await apiGet<Single<ChecklistDetail>>(`/kerangka-acuan/${id}`, { institusi_id: DEFAULT_INSTITUSI });
    detail = res.data;
  } catch {
    notFound();
  }

  const badanRes = await apiGet<Paginated<BadanRujukan>>("/badan-rujukan", { institusi_id: DEFAULT_INSTITUSI, per_page: 100 }).catch(() => null);
  const badanList = badanRes?.data ?? [];

  const { kerangka, butir, ringkasan } = detail;
  const kerangkaId = kerangka.id;

  return (
    <div>
      <PageHeader
        title={kerangka.nama}
        subtitle={`${kerangka.badan_rujukan ?? "—"}${kerangka.versi ? " · v" + kerangka.versi : ""}`}
        actions={
          <div className="flex items-center gap-3">
            <CreateButirButton kerangkaId={kerangkaId} />
            <Link href="/checklist-acuan" className="text-sm text-brand-700 hover:underline">
              ← Semua kerangka
            </Link>
          </div>
        }
      />

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Stat label="Pemenuhan" value={`${ringkasan.persen}%`} hint={`${ringkasan.terpenuhi}/${ringkasan.total} terpenuhi`} />
        <Stat label="Terpenuhi" value={ringkasan.terpenuhi} />
        <Stat label="Sebagian" value={ringkasan.sebagian} />
        <Stat label="Belum" value={ringkasan.belum} hint={ringkasan.tidak_relevan > 0 ? `${ringkasan.tidak_relevan} tidak relevan` : undefined} />
      </div>

      {/* Progress bar */}
      <Card className="mt-4">
        <div className="p-4">
          <div className="h-3 w-full overflow-hidden rounded-full bg-gray-100">
            <div className="h-full rounded-full bg-brand-600 transition-all" style={{ width: `${ringkasan.persen}%` }} />
          </div>
        </div>
      </Card>

      <Card className="mt-6">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Butir Acuan</h2>
          <p className="text-xs text-muted">Tandai status pemenuhan tiap butir terhadap kurikulum institusi Anda.</p>
        </div>
        {butir.length === 0 ? (
          <EmptyState title="Belum ada butir" hint="Tambahkan butir acuan lewat tombol di atas." />
        ) : (
          <Table>
            <thead>
              <tr>
                <Th>Kode</Th>
                <Th>Kategori</Th>
                <Th>Deskripsi</Th>
                <Th>Status</Th>
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {butir.map((b) => (
                <tr key={b.id} className="align-top hover:bg-gray-50">
                  <Td className="font-medium text-ink">
                    {b.kode ?? "—"}
                    {!b.wajib && <span className="ml-1 text-xs text-muted">(opsional)</span>}
                  </Td>
                  <Td><Badge tone="neutral">{KATEGORI_LABEL[b.kategori] ?? b.kategori}</Badge></Td>
                  <Td className="max-w-md text-muted">
                    {b.deskripsi}
                    {b.catatan && <p className="mt-1 text-xs text-brand-700">Catatan: {b.catatan}</p>}
                  </Td>
                  <Td>
                    <StatusControl b={b} kerangkaId={kerangkaId} />
                  </Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <EditButirButton b={b} kerangkaId={kerangkaId} />
                      <DeleteButirButton b={b} kerangkaId={kerangkaId} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  );
}
