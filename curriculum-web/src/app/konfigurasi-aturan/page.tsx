import { apiGet, type KonfigurasiAturan } from "@/lib/api";
import { getCurrentUser } from "@/lib/auth";
import { PageHeader, Card, CardBody } from "@/components/ui";
import { KonfigurasiForms, InstitusiPicker } from "./forms";

type UnitOpsi = { id: number; nama: string; jenis: string; parent_id: number | null };

export default async function KonfigurasiAturanPage({ searchParams }: { searchParams: Promise<{ institusi?: string }> }) {
  const { institusi } = await searchParams;
  const user = await getCurrentUser();
  // Akun tanpa institusi (superadmin) wajib memilih unit tujuan secara eksplisit.
  const lintasInstitusi = !!user && user.institusi_id == null;
  let units: UnitOpsi[] = [];
  let target: number | undefined;
  if (lintasInstitusi) {
    units = await apiGet<{ data: UnitOpsi[] }>("/institusi").then((r) => r.data).catch(() => []);
    const diminta = Number(institusi);
    target = units.some((u) => u.id === diminta) ? diminta : (units.find((u) => u.parent_id == null) ?? units[0])?.id;
  }

  let list: KonfigurasiAturan[] = [];
  let error: string | null = null;
  try {
    const res = await apiGet<{ data: KonfigurasiAturan[] }>("/konfigurasi-aturan", { institusi_id: target });
    list = res.data;
  } catch {
    error = "Tidak dapat memuat konfigurasi aturan. Pastikan backend berjalan di :8100.";
  }

  return (
    <div>
      <PageHeader
        title="Konfigurasi Aturan"
        subtitle="Nilai aturan otoritatif (jumlah minggu, bobot, konversi SKS→jam) diisi manual oleh admin/Kaprodi — bukan hasil AI, demi menjaga akurasi."
        actions={lintasInstitusi && target ? <InstitusiPicker units={units} value={target} /> : undefined}
      />
      {lintasInstitusi && target && (
        <p className="mb-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-xs text-brand-800">
          Aturan yang disimpan berlaku untuk unit terpilih dan diwariskan ke fakultas/prodi di bawahnya yang belum mengatur sendiri.
        </p>
      )}
      {error ? (
        <Card>
          <CardBody>
            <p className="text-sm text-red-600">{error}</p>
          </CardBody>
        </Card>
      ) : (
        <KonfigurasiForms key={target ?? "self"} list={list} institusiId={target} />
      )}
    </div>
  );
}
