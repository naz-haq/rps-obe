"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Modal } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { imporRpsLama } from "../actions";

type CpmkRow = { kode: string; deskripsi: string; cpl_kode: string[]; taksonomi_kode: string[] };
type SubRow = { kode: string; cpmk_kode: string; deskripsi: string; taksonomi_kode: string[]; indikator: string[] };
type MingguRow = {
  minggu_ke: number;
  sub_cpmk_kode: string;
  indikator: string;
  kriteria_penilaian: string;
  metode_pembelajaran: string;
  bentuk_luring: string;
  bentuk_daring: string;
  pengalaman_belajar: string;
  materi_pustaka: string;
  bobot_penilaian: number | null;
};
type KomponenRow = {
  nama: string;
  jenis: string;
  bobot_persen: number | null;
  sub_cpmk_kode: string;
  minggu_ke: number | null;
  instrumen: string;
};

type Parsed = { cpmk: CpmkRow[]; sub_cpmk: SubRow[]; minggu: MingguRow[]; komponen: KomponenRow[] };

const cell = (v: unknown) => (v == null ? "" : String(v).trim());
const splitList = (v: unknown) =>
  cell(v)
    .split(/[;\n]/)
    .map((s) => s.trim())
    .filter(Boolean);
const num = (v: unknown): number | null => {
  const n = Number(cell(v).replace(/[^0-9.,-]/g, "").replace(",", "."));
  return Number.isFinite(n) && cell(v) !== "" ? n : null;
};

/** Buang baris judul bila sel pertama memuat kata kunci header. */
function stripHeader(rows: unknown[][], keyword: string): unknown[][] {
  if (rows.length === 0) return rows;
  const first = cell(rows[0][0]).toLowerCase();
  return first.includes(keyword) ? rows.slice(1) : rows;
}

type SheetData = { sheet: string; data: unknown[][] };

/** Peta sheet→baris. read-excel-file v9 mengembalikan {sheet,data}[] untuk
 *  workbook multi-sheet (opsi {sheet} diabaikan); fallback ke bentuk rows datar. */
function petaSheet(raw: unknown): Map<string, unknown[][]> {
  const map = new Map<string, unknown[][]>();
  if (Array.isArray(raw) && raw.length > 0) {
    const first = raw[0];
    if (first && typeof first === "object" && !Array.isArray(first) && "data" in first) {
      for (const s of raw as SheetData[]) {
        map.set(String(s.sheet), Array.isArray(s.data) ? s.data : []);
      }
    }
  }
  return map;
}

async function parseWorkbook(file: File): Promise<Parsed> {
  const readXlsx = (await import("read-excel-file/browser")).default as unknown as (
    f: File,
  ) => Promise<unknown>;

  const byName = petaSheet(await readXlsx(file));
  const rowsOf = (nama: string) =>
    (byName.get(nama) ?? []).filter((r) => Array.isArray(r) && r.some((c) => cell(c) !== ""));

  const cpmkRows = stripHeader(rowsOf("CPMK"), "kode");
  const subRows = stripHeader(rowsOf("Sub-CPMK"), "kode");
  const mingguRows = stripHeader(rowsOf("Mingguan"), "minggu");
  const nilaiRows = stripHeader(rowsOf("Penilaian"), "nama");

  const cpmk: CpmkRow[] = cpmkRows
    .map((r) => ({ kode: cell(r[0]), deskripsi: cell(r[1]), cpl_kode: splitList(r[2]), taksonomi_kode: splitList(r[3]) }))
    .filter((x) => x.kode || x.deskripsi);

  const sub_cpmk: SubRow[] = subRows
    .map((r) => ({
      kode: cell(r[0]),
      cpmk_kode: cell(r[1]),
      deskripsi: cell(r[2]),
      taksonomi_kode: splitList(r[3]),
      indikator: splitList(r[4]),
    }))
    .filter((x) => x.kode || x.deskripsi);

  const minggu: MingguRow[] = mingguRows
    .map((r) => ({
      minggu_ke: num(r[0]) ?? 0,
      sub_cpmk_kode: cell(r[1]),
      indikator: cell(r[2]),
      kriteria_penilaian: cell(r[3]),
      metode_pembelajaran: cell(r[4]),
      bentuk_luring: cell(r[5]),
      bentuk_daring: cell(r[6]),
      pengalaman_belajar: cell(r[7]),
      materi_pustaka: cell(r[8]),
      bobot_penilaian: num(r[9]),
    }))
    .filter((x) => x.minggu_ke > 0);

  const komponen: KomponenRow[] = nilaiRows
    .map((r) => ({
      nama: cell(r[0]),
      jenis: cell(r[1]) || "tugas",
      bobot_persen: num(r[2]),
      sub_cpmk_kode: cell(r[3]),
      minggu_ke: num(r[4]),
      instrumen: cell(r[5]),
    }))
    .filter((x) => x.nama);

  return { cpmk, sub_cpmk, minggu, komponen };
}

export function ImporRpsPanel({ sessionId }: { sessionId: number }) {
  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 className="text-sm font-semibold text-ink">Impor RPS Lama (Excel)</h3>
          <p className="text-xs text-muted">
            Punya RPS lama yang belum ada di sistem? Unduh template, isi, lalu unggah untuk mengisi
            tahap CPMK hingga Penilaian tanpa generate AI. Hasil tetap bisa ditinjau &amp; disunting sebelum Commit.
          </p>
        </div>
        <Modal trigger="Impor dari Excel" triggerVariant="secondary" triggerSize="sm" title="Impor RPS Lama dari Excel">
          {(close) => <ImporForm sessionId={sessionId} close={close} />}
        </Modal>
      </div>
    </div>
  );
}

function ImporForm({ sessionId, close }: { sessionId: number; close: () => void }) {
  const router = useRouter();
  const [parsed, setParsed] = useState<Parsed | null>(null);
  const [fileName, setFileName] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  const onFile = async (file: File | undefined) => {
    if (!file) return;
    setError(null);
    setFileName(file.name);
    try {
      const hasil = await parseWorkbook(file);
      const total = hasil.cpmk.length + hasil.sub_cpmk.length + hasil.minggu.length + hasil.komponen.length;
      if (total === 0) {
        setParsed(null);
        setError("Tidak ada data terbaca. Pastikan memakai template dan sheet-nya berisi.");
        return;
      }
      setParsed(hasil);
    } catch {
      setParsed(null);
      setError("Gagal membaca berkas. Pastikan format .xlsx sesuai template.");
    }
  };

  const terapkan = () => {
    if (!parsed) return;
    setError(null);
    startTransition(async () => {
      const res = await imporRpsLama(sessionId, {
        cpmk: parsed.cpmk,
        sub_cpmk: parsed.sub_cpmk,
        minggu: parsed.minggu,
        komponen: parsed.komponen,
      });
      if (!res.ok) {
        setError(res.message ?? "Impor gagal.");
        return;
      }
      close();
      router.refresh();
    });
  };

  const bobotMinggu = parsed?.minggu.reduce((a, m) => a + (m.bobot_penilaian ?? 0), 0) ?? 0;
  const bobotKomponen = parsed?.komponen.reduce((a, k) => a + (k.bobot_persen ?? 0), 0) ?? 0;

  return (
    <div className="space-y-4">
      <div className="rounded-lg border border-border bg-gray-50/60 p-3 text-xs text-gray-600">
        <p className="mb-1 font-semibold text-ink">Langkah:</p>
        <ol className="list-inside list-decimal space-y-0.5">
          <li>
            Unduh{" "}
            <a href="/template-impor-rps.xlsx" download className="font-medium text-brand-700 hover:underline">
              template Excel
            </a>{" "}
            (4 sheet: CPMK, Sub-CPMK, Mingguan, Penilaian).
          </li>
          <li>Isi sesuai RPS lama Anda — sheet kosong dilewati.</li>
          <li>Unggah kembali di bawah, tinjau ringkasan, lalu Terapkan.</li>
        </ol>
      </div>

      <label className="flex cursor-pointer flex-col items-center gap-1 rounded-xl border border-dashed border-brand-300 bg-brand-50/40 px-4 py-6 text-center text-sm text-brand-700 hover:bg-brand-50">
        <span className="text-2xl" aria-hidden>
          ⬆
        </span>
        <span className="font-medium">Klik untuk memilih berkas Excel (.xlsx)</span>
        <span className="text-xs text-muted">{fileName || "Belum ada berkas dipilih"}</span>
        <input
          type="file"
          accept=".xlsx"
          className="hidden"
          onChange={(e) => {
            const f = e.target.files?.[0];
            void onFile(f);
            e.target.value = "";
          }}
        />
      </label>

      {error && <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{error}</p>}

      {parsed && (
        <div className="space-y-2">
          <p className="text-xs font-semibold text-ink">Ringkasan yang akan diimpor:</p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <RingkasKartu label="CPMK" nilai={parsed.cpmk.length} />
            <RingkasKartu label="Sub-CPMK" nilai={parsed.sub_cpmk.length} />
            <RingkasKartu label="Minggu" nilai={parsed.minggu.length} />
            <RingkasKartu label="Komponen" nilai={parsed.komponen.length} />
          </div>
          <ul className="list-inside list-disc space-y-0.5 text-[11px] text-muted">
            {parsed.komponen.length > 0 && bobotKomponen !== 100 && (
              <li className="text-amber-700">Total bobot komponen penilaian {bobotKomponen}% (idealnya 100%) — bisa dirapikan setelah impor.</li>
            )}
            {parsed.minggu.length > 0 && bobotMinggu > 0 && bobotMinggu !== 100 && (
              <li className="text-amber-700">Total bobot mingguan {bobotMinggu}% — periksa kembali bila perlu.</li>
            )}
            <li>Tahap yang diimpor langsung tersimpan sebagai draf tersetujui dan tetap dapat disunting sebelum Commit.</li>
          </ul>
        </div>
      )}

      <div className="flex justify-end gap-2 border-t border-border pt-3">
        <button type="button" onClick={close} className={buttonClass("secondary", "sm")} disabled={pending}>
          Batal
        </button>
        <button
          type="button"
          onClick={terapkan}
          disabled={!parsed || pending}
          className={buttonClass("primary", "sm")}
        >
          {pending ? "Mengimpor…" : "Terapkan"}
        </button>
      </div>
    </div>
  );
}

function RingkasKartu({ label, nilai }: { label: string; nilai: number }) {
  return (
    <div className="rounded-lg border border-border bg-gray-50/60 px-3 py-2 text-center">
      <div className="text-lg font-bold text-ink">{nilai}</div>
      <div className="text-[10px] uppercase tracking-wide text-gray-400">{label}</div>
    </div>
  );
}
