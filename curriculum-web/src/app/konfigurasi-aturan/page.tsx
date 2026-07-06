import { apiGet, type KonfigurasiAturan } from "@/lib/api";
import { PageHeader, Card, CardBody } from "@/components/ui";
import { KonfigurasiForms } from "./forms";

export default async function KonfigurasiAturanPage() {
  let list: KonfigurasiAturan[] = [];
  let error: string | null = null;
  try {
    const res = await apiGet<{ data: KonfigurasiAturan[] }>("/konfigurasi-aturan", { institusi_id: 1 });
    list = res.data;
  } catch {
    error = "Tidak dapat memuat konfigurasi aturan. Pastikan backend berjalan di :8100.";
  }

  return (
    <div>
      <PageHeader
        title="Konfigurasi Aturan"
        subtitle="Nilai aturan otoritatif (jumlah minggu, bobot, konversi SKS→jam) diisi manual oleh admin/Kaprodi — bukan hasil AI, demi menjaga akurasi."
      />
      {error ? (
        <Card>
          <CardBody>
            <p className="text-sm text-red-600">{error}</p>
          </CardBody>
        </Card>
      ) : (
        <KonfigurasiForms list={list} />
      )}
    </div>
  );
}
