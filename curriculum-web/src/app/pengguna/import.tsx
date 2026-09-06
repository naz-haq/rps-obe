"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Modal, SelectField } from "@/components/modal";
import { buttonClass, Badge } from "@/components/ui";
import type { InstitusiData, RoleData } from "@/lib/api";
import { importUsers, type ImportUsersRingkasan } from "./actions";

const CONTOH =
  "nama;nidn;email;jabatan;peran\nBudi Santoso;0912088801;budi@kampus.ac.id;Lektor;dosen\nSiti Aminah;0912088802;siti@kampus.ac.id;Asisten Ahli;dosen,koordinator-mk";

/** Uraikan CSV/TSV: deteksi delimiter (; , tab), hormati kutip ganda. */
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
        } else cur += c;
      } else if (c === '"') inQuotes = true;
      else if (c === delim) { out.push(cur.trim()); cur = ""; }
      else cur += c;
    }
    out.push(cur.trim());
    return out;
  };
  return lines.map(parseLine);
}

function normalizeXlsx(rows: unknown[][]): string[][] {
  const cell = (v: unknown): string => {
    if (v == null) return "";
    if (v instanceof Date) {
      const y = v.getFullYear();
      const m = String(v.getMonth() + 1).padStart(2, "0");
      const d = String(v.getDate()).padStart(2, "0");
      return `${y}-${m}-${d}`;
    }
    if (typeof v === "boolean") return v ? "1" : "0";
    return String(v).trim();
  };
  return rows
    .map((row) => (Array.isArray(row) ? row.map(cell) : []))
    .filter((row) => row.some((c) => c !== ""));
}

function unwrapSheets(result: unknown): unknown[][] {
  if (!Array.isArray(result) || result.length === 0) return [];
  const first = result[0] as Record<string, unknown> | unknown[];
  const isSheetShape = first != null && typeof first === "object" && !Array.isArray(first) && "data" in first;
  if (isSheetShape) {
    const sheets = result as { data?: unknown[][] }[];
    const withData = sheets.find((s) => Array.isArray(s.data) && s.data.length > 1);
    return (withData?.data ?? sheets[0]?.data ?? []) as unknown[][];
  }
  return result as unknown[][];
}

const FIELDS = [
  { name: "nama", wajib: true },
  { name: "nidn", wajib: true },
  { name: "email" },
  { name: "jabatan" },
  { name: "peran" },
];

export function ImportUsersButton({ roles, institusi }: { roles: RoleData[]; institusi: InstitusiData[] }) {
  return (
    <Modal trigger="⬆ Impor Pengguna" title="Impor Pengguna Massal" triggerVariant="secondary" triggerSize="sm" size="lg">
      {(close) => <ImportForm roles={roles} institusi={institusi} close={close} />}
    </Modal>
  );
}

function ImportForm({
  roles,
  institusi,
  close,
}: {
  roles: RoleData[];
  institusi: InstitusiData[];
  close: () => void;
}) {
  const router = useRouter();
  const [rows, setRows] = useState<unknown[][]>([]);
  const [fileName, setFileName] = useState("");
  const [institusiId, setInstitusiId] = useState("");
  const [parseError, setParseError] = useState<string | null>(null);
  const [hasil, setHasil] = useState<ImportUsersRingkasan | null>(null);
  const [pending, startTransition] = useTransition();

  const dataRows = rows.length > 1 ? rows.length - 1 : 0;
  const institusiOptions = [
    { value: "", label: "— Ikuti kolom / kosong —" },
    ...institusi.map((i) => ({ value: String(i.id), label: i.nama })),
  ];

  async function onFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    setParseError(null);
    setHasil(null);
    setRows([]);
    if (!file) return;
    setFileName(file.name);
    try {
      if (file.name.toLowerCase().endsWith(".csv")) {
        const parsed = parseDelimited(await file.text());
        setRows(parsed);
        if (parsed.length <= 1) setParseError("Berkas terbaca, tetapi tidak ada baris data (header di baris pertama).");
      } else {
        const readXlsxFile = (await import("read-excel-file/browser")).default;
        const norm = normalizeXlsx(unwrapSheets(await readXlsxFile(file)));
        setRows(norm);
        if (norm.length <= 1) setParseError("Berkas .xlsx terbaca, tetapi tidak ada baris data di sheet pertama.");
      }
    } catch {
      setParseError("Gagal membaca berkas. Pastikan format .xlsx (bukan .xls lama) atau .csv yang valid.");
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
      const res = await importUsers(rows, institusiId ? Number(institusiId) : undefined);
      setHasil(res);
      if (res.ok) router.refresh();
    });
  }

  return (
    <div className="space-y-4">
      <div className="rounded-lg border border-border bg-gray-50/60 px-3 py-2.5 text-xs text-gray-600">
        <p className="font-semibold text-ink">Kolom yang dikenali (baris pertama = header):</p>
        <div className="mt-1.5 flex flex-wrap gap-1.5">
          {FIELDS.map((f) => (
            <Badge key={f.name} tone={f.wajib ? "brand" : "neutral"}>
              {f.name}
              {f.wajib ? " *" : ""}
            </Badge>
          ))}
        </div>
        <p className="mt-1.5 text-[11px] text-muted">
          Kolom <b>nama</b> &amp; <b>nidn</b> wajib. NIDN = kunci login (baris dengan NIDN sama akan diperbarui).
          Kata sandi awal = NIDN bila kolom sandi kosong. Peran boleh beberapa, dipisah koma
          (mis. <code>dosen,koordinator-mk</code>).
        </p>
      </div>

      <div className="rounded-lg border border-border bg-gray-50/60 px-3 py-2.5 text-xs">
        <p className="mb-1 font-semibold text-ink">Peran yang tersedia:</p>
        <div className="flex flex-wrap gap-1.5">
          {roles.map((r) => (
            <Badge key={r.id} tone="neutral">{r.name}</Badge>
          ))}
        </div>
      </div>

      <SelectField
        label="Unit/Institusi default (bila kolom kosong)"
        name="institusi_id"
        defaultValue=""
        onChange={(e) => setInstitusiId(e.target.value)}
        options={institusiOptions}
      />

      <label className="block">
        <span className="text-xs font-semibold text-gray-600">Berkas Excel (.xlsx) atau CSV</span>
        <input
          type="file"
          accept=".xlsx,.csv"
          onChange={onFile}
          className="mt-1 block w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
        />
      </label>

      <details className="text-xs">
        <summary className="cursor-pointer text-muted hover:text-ink">Atau tempel CSV (klik untuk contoh)</summary>
        <textarea
          defaultValue={CONTOH}
          onChange={(e) => pasteCsv(e.target.value)}
          rows={4}
          className="mt-2 w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 font-mono text-[11px] outline-none focus:border-brand-400"
        />
      </details>

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
          {hasil.galat.length > 0 && (
            <ul className="mt-1.5 list-inside list-disc space-y-0.5">
              {hasil.galat.slice(0, 8).map((g, i) => (
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
        <button type="button" disabled={pending || dataRows === 0} onClick={doImport} className={buttonClass("primary", "sm")}>
          {pending ? "Mengimpor…" : `Impor ${dataRows > 0 ? dataRows + " baris" : ""}`}
        </button>
      </div>
    </div>
  );
}
