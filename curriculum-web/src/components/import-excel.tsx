"use client";

import { useState, useTransition } from "react";
import { useRouter, usePathname } from "next/navigation";
import { Modal } from "@/components/modal";
import { buttonClass, Badge } from "@/components/ui";
import { importExcelRows, type ImportJenis, type ImportRingkasan } from "@/lib/import-actions";

type FieldHint = { name: string; wajib?: boolean };

/**
 * Uraikan teks CSV menjadi matriks string. Mendeteksi delimiter otomatis
 * (koma / titik-koma / tab — Excel lokal Indonesia sering pakai titik-koma)
 * dan menghormati tanda kutip ganda (mis. deskripsi yang memuat koma).
 */
function parseDelimited(text: string): string[][] {
  const lines = text.split(/\r\n|\r|\n/).filter((l) => l.trim() !== "");
  if (lines.length === 0) return [];
  const first = lines[0];
  const count = (ch: string) => first.split(ch).length - 1;
  const delim = count(";") > count(",") ? ";" : count("\t") > count(",") ? "\t" : ",";

  const parseLine = (line: string): string[] => {
    const out: string[] = [];
    let cur = "";
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
      const c = line[i];
      if (inQuotes) {
        if (c === '"') {
          if (line[i + 1] === '"') { cur += '"'; i++; } else { inQuotes = false; }
        } else {
          cur += c;
        }
      } else if (c === '"') {
        inQuotes = true;
      } else if (c === delim) {
        out.push(cur.trim());
        cur = "";
      } else {
        cur += c;
      }
    }
    out.push(cur.trim());
    return out;
  };

  return lines.map(parseLine);
}

/**
 * Tombol + modal impor Excel/CSV yang dapat dipakai ulang di halaman entitas.
 * Parsing .xlsx dilakukan di klien (read-excel-file, aman) → dikirim sebagai
 * rows 2D; CSV di-split langsung. Pemetaan kolom otomatis di server.
 */
export function ImportExcelButton({
  jenis,
  kurikulumId,
  institusiId,
  label = "Import Excel",
  fields,
  contoh,
}: {
  jenis: ImportJenis;
  kurikulumId: number;
  institusiId: number;
  label?: string;
  fields: FieldHint[];
  contoh?: string;
}) {
  return (
    <Modal trigger={`⬆ ${label}`} title={`Impor ${label}`} triggerVariant="secondary" triggerSize="sm" size="lg">
      {(close) => (
        <ImportForm jenis={jenis} kurikulumId={kurikulumId} institusiId={institusiId} fields={fields} contoh={contoh} close={close} />
      )}
    </Modal>
  );
}

function ImportForm({
  jenis,
  kurikulumId,
  institusiId,
  fields,
  contoh,
  close,
}: {
  jenis: ImportJenis;
  kurikulumId: number;
  institusiId: number;
  fields: FieldHint[];
  contoh?: string;
  close: () => void;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const [rows, setRows] = useState<unknown[][]>([]);
  const [fileName, setFileName] = useState<string>("");
  const [parseError, setParseError] = useState<string | null>(null);
  const [hasil, setHasil] = useState<ImportRingkasan | null>(null);
  const [pending, startTransition] = useTransition();

  const dataRows = rows.length > 1 ? rows.length - 1 : 0;

  async function onFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    setParseError(null);
    setHasil(null);
    setRows([]);
    if (!file) return;
    setFileName(file.name);
    try {
      if (file.name.toLowerCase().endsWith(".csv")) {
        const text = await file.text();
        setRows(parseDelimited(text));
      } else {
        const readXlsxFile = (await import("read-excel-file/browser")).default;
        const parsed = await readXlsxFile(file);
        setRows(parsed as unknown as unknown[][]);
      }
    } catch {
      setParseError("Gagal membaca berkas. Pastikan format .xlsx atau .csv valid.");
    }
  }

  function pasteCsv(text: string) {
    setParseError(null);
    setHasil(null);
    setFileName("");
    const parsed = parseDelimited(text);
    setRows(parsed.length > 1 ? parsed : []);
  }

  function doImport() {
    startTransition(async () => {
      const res = await importExcelRows(jenis, kurikulumId, institusiId, rows, pathname);
      setHasil(res);
      if (res.ok) router.refresh();
    });
  }

  return (
    <div className="space-y-4">
      <div className="rounded-lg border border-border bg-gray-50/60 px-3 py-2.5 text-xs text-gray-600">
        <p className="font-semibold text-ink">Kolom yang dikenali (baris pertama = header):</p>
        <div className="mt-1.5 flex flex-wrap gap-1.5">
          {fields.map((f) => (
            <Badge key={f.name} tone={f.wajib ? "brand" : "neutral"}>
              {f.name}
              {f.wajib ? " *" : ""}
            </Badge>
          ))}
        </div>
        <p className="mt-1.5 text-[11px] text-muted">
          Kolom bertanda <b>*</b> wajib ada. Header dipetakan otomatis; data dengan kode/nama sama akan diperbarui.
        </p>
      </div>

      <label className="block">
        <span className="text-xs font-semibold text-gray-600">Berkas Excel (.xlsx) atau CSV</span>
        <input
          type="file"
          accept=".xlsx,.csv"
          onChange={onFile}
          className="mt-1 block w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
        />
      </label>

      {contoh && (
        <details className="text-xs">
          <summary className="cursor-pointer text-muted hover:text-ink">Atau tempel CSV (klik untuk contoh)</summary>
          <textarea
            defaultValue={contoh}
            onChange={(e) => pasteCsv(e.target.value)}
            rows={4}
            className="mt-2 w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 font-mono text-[11px] outline-none focus:border-brand-400"
          />
        </details>
      )}

      {fileName && <p className="text-xs text-muted">Berkas: {fileName}</p>}
      {rows.length > 0 && (
        <p className="text-xs text-emerald-700">
          Terbaca <b>{dataRows}</b> baris data (di luar header).
        </p>
      )}
      {parseError && <p className="text-xs text-rose-600">{parseError}</p>}

      {hasil && (
        <div
          className={`rounded-lg border px-3 py-2.5 text-xs ${
            hasil.ok ? "border-emerald-200 bg-emerald-50 text-emerald-800" : "border-rose-200 bg-rose-50 text-rose-700"
          }`}
        >
          {hasil.ok ? (
            <p>
              Impor selesai — <b>{hasil.dibuat}</b> dibuat, <b>{hasil.diperbarui}</b> diperbarui,{" "}
              <b>{hasil.dilewati}</b> dilewati.
            </p>
          ) : (
            <p>{hasil.message}</p>
          )}
          {(hasil.galat ?? []).length > 0 && (
            <ul className="mt-1.5 list-inside list-disc space-y-0.5">
              {(hasil.galat ?? []).slice(0, 8).map((g, i) => (
                <li key={i}>{g}</li>
              ))}
            </ul>
          )}
        </div>
      )}

      <div className="flex justify-end gap-2 border-t border-border pt-3">
        <button type="button" onClick={close} className={buttonClass("secondary", "sm")}>
          {hasil?.ok ? "Tutup" : "Batal"}
        </button>
        <button
          type="button"
          disabled={pending || dataRows === 0}
          onClick={doImport}
          className={buttonClass("primary", "sm")}
        >
          {pending ? "Mengimpor…" : `Impor ${dataRows > 0 ? dataRows + " baris" : ""}`}
        </button>
      </div>
    </div>
  );
}
