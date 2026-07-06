import { notFound } from "next/navigation";
import { apiGet, type Single, type Paginated, type GenerateSession, type MataKuliah, type Cpl, type Taksonomi, type KonfigurasiAturan } from "@/lib/api";
import { Builder } from "./builder";
import { PrasyaratBanner } from "./prasyarat";

export default async function GeneratorDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  let session: GenerateSession;
  try {
    const res = await apiGet<Single<GenerateSession>>(`/generate-sessions/${id}`);
    session = res.data;
  } catch {
    notFound();
  }

  // Ambil daftar CPL kurikulum MK ini untuk matriks & self-check.
  let cplList: Cpl[] = [];
  let estimasiWaktu = "";
  let mkJenis = "";
  try {
    const mk = await apiGet<Single<MataKuliah>>(`/mata-kuliah/${session.mk_id}`);
    estimasiWaktu = mk.data.estimasi_waktu?.teks ?? "";
    mkJenis = mk.data.jenis_mk ?? "";
    const kurikulumId = mk.data.kurikulum_id;
    if (kurikulumId) {
      const cpl = await apiGet<Paginated<Cpl>>("/cpl", { kurikulum_id: kurikulumId, per_page: 100 });
      cplList = cpl.data;
    }
  } catch {
    cplList = [];
  }

  // Daftar taksonomi (global + tenant) untuk dropdown level taksonomi CPMK/Sub-CPMK.
  let taksonomiList: Taksonomi[] = [];
  try {
    const tak = await apiGet<{ data: Taksonomi[] }>("/taksonomi", { institusi_id: 1, per_page: 200 });
    taksonomiList = tak.data;
  } catch {
    taksonomiList = [];
  }

  // Konfigurasi aturan yang menjadi keterkaitan generate (konversi SKS, minggu, bobot).
  let aturan: KonfigurasiAturan[] = [];
  try {
    const res = await apiGet<{ data: KonfigurasiAturan[] }>("/konfigurasi-aturan", { institusi_id: 1 });
    aturan = res.data;
  } catch {
    aturan = [];
  }
  const punya = (jenis: string) => aturan.some((a) => a.jenis_aturan === jenis);

  return (
    <div className="space-y-6">
      <PrasyaratBanner
        cplCount={cplList.length}
        taksonomiCount={taksonomiList.length}
        mkJenis={mkJenis}
        hasKonversi={punya("konversi_sks")}
        hasMinggu={punya("jumlah_minggu")}
        hasBobotTeori={punya("bobot_teori")}
        hasBobotPraktikum={punya("bobot_praktikum")}
      />
      <Builder session={session} cplList={cplList} taksonomiList={taksonomiList} estimasiWaktu={estimasiWaktu} />
    </div>
  );
}
