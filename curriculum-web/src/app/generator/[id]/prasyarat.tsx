import Link from "next/link";

export type Prasyarat = {
  cplCount: number;
  taksonomiCount: number;
  mkJenis: string;
  hasKonversi: boolean;
  hasMinggu: boolean;
  hasBobotTeori: boolean;
  hasBobotPraktikum: boolean;
};

type Item = { level: "kritis" | "saran"; teks: string; href: string; aksi: string };

/**
 * Banner prasyarat sebelum generate RPS.
 * Memeriksa keterkaitan data: CPL kurikulum, master Taksonomi, dan
 * Konfigurasi Aturan (konversi SKS, jumlah minggu, bobot penilaian).
 * "kritis" = menghambat kualitas generate; "saran" = memakai nilai default.
 */
export function PrasyaratBanner(p: Prasyarat) {
  const items: Item[] = [];

  if (p.cplCount === 0) {
    items.push({
      level: "kritis",
      teks: "Kurikulum mata kuliah ini belum memiliki CPL. Generator tidak dapat memetakan CPMK → CPL sehingga hasil tidak lengkap.",
      href: "/kurikulum",
      aksi: "Lengkapi CPL di Peta Kurikulum",
    });
  }
  if (p.taksonomiCount === 0) {
    items.push({
      level: "saran",
      teks: "Master Taksonomi masih kosong — dropdown level taksonomi (C/A/P) untuk CPMK & Sub-CPMK akan kosong.",
      href: "/taksonomi",
      aksi: "Isi Taksonomi",
    });
  }
  if (!p.hasKonversi) {
    items.push({
      level: "saran",
      teks: "Aturan Konversi SKS belum diatur — estimasi waktu mingguan memakai nilai default (TM 50′, PT/BM 60′, praktik 170′).",
      href: "/konfigurasi-aturan",
      aksi: "Atur Konversi SKS",
    });
  }
  if (!p.hasMinggu) {
    items.push({
      level: "saran",
      teks: "Aturan Jumlah Minggu belum diatur — rencana mingguan memakai default 16 minggu.",
      href: "/konfigurasi-aturan",
      aksi: "Atur Jumlah Minggu",
    });
  }
  const praktikum = p.mkJenis === "praktikum";
  if (praktikum ? !p.hasBobotPraktikum : !p.hasBobotTeori) {
    items.push({
      level: "saran",
      teks: `Aturan Bobot Penilaian (${praktikum ? "MK Praktikum" : "MK Teori"}) belum diatur — komposisi bobot komponen memakai default.`,
      href: "/konfigurasi-aturan",
      aksi: "Atur Bobot Penilaian",
    });
  }

  if (items.length === 0) return null;

  const kritis = items.filter((i) => i.level === "kritis");
  const saran = items.filter((i) => i.level === "saran");

  return (
    <div className="space-y-3">
      {kritis.length > 0 && (
        <Blok
          tone="kritis"
          judul="Prasyarat wajib belum lengkap"
          catatan="Sebaiknya lengkapi ini lebih dulu agar hasil generate akurat."
          items={kritis}
        />
      )}
      {saran.length > 0 && (
        <Blok
          tone="saran"
          judul="Sebagian aturan belum diatur"
          catatan="Generate tetap bisa dijalankan, namun bagian ini akan memakai nilai default."
          items={saran}
        />
      )}
    </div>
  );
}

function Blok({
  tone,
  judul,
  catatan,
  items,
}: {
  tone: "kritis" | "saran";
  judul: string;
  catatan: string;
  items: Item[];
}) {
  const kritis = tone === "kritis";
  return (
    <div
      className={`rounded-xl border px-4 py-3.5 ${
        kritis ? "border-rose-200 bg-rose-50" : "border-amber-200 bg-amber-50"
      }`}
    >
      <p className={`flex items-center gap-1.5 text-sm font-semibold ${kritis ? "text-rose-800" : "text-amber-800"}`}>
        <span aria-hidden>{kritis ? "⛔" : "⚠️"}</span> {judul}
      </p>
      <p className={`mt-0.5 text-xs ${kritis ? "text-rose-700" : "text-amber-700"}`}>{catatan}</p>
      <ul className="mt-2.5 space-y-2">
        {items.map((it, i) => (
          <li key={i} className="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between">
            <span className={`text-xs ${kritis ? "text-rose-700" : "text-amber-800"}`}>{it.teks}</span>
            <Link
              href={it.href}
              className={`shrink-0 self-start rounded-lg px-2.5 py-1 text-xs font-semibold transition sm:self-auto ${
                kritis
                  ? "bg-rose-600 text-white hover:bg-rose-700"
                  : "border border-amber-300 bg-white text-amber-800 hover:bg-amber-100"
              }`}
            >
              {it.aksi} →
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
