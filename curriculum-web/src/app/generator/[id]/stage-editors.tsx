"use client";

import { buttonClass } from "@/components/ui";
import type { Taksonomi } from "@/lib/api";
import {
  type CpmkItem,
  type SubCpmkItem,
  type MingguItem,
  type KomponenItem,
} from "./draft";

const inputCls =
  "w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs outline-none focus:border-brand-400";
const labelCls = "text-[10px] font-semibold uppercase tracking-wide text-gray-400";

function RowShell({ children, onRemove }: { children: React.ReactNode; onRemove: () => void }) {
  return (
    <div className="relative rounded-xl border border-border bg-gray-50/50 p-3">
      <button
        type="button"
        onClick={onRemove}
        className="absolute right-2 top-2 text-xs text-gray-400 hover:text-rose-600"
        title="Hapus"
      >
        ✕
      </button>
      {children}
    </div>
  );
}

/** Baca berkas .xlsx/.csv jadi matriks string. */
async function parseSpreadsheet(file: File): Promise<string[][]> {
  if (file.name.toLowerCase().endsWith(".csv")) {
    const text = await file.text();
    return text
      .split(/\r?\n/)
      .filter((l) => l.trim())
      .map((l) => l.split(",").map((c) => c.trim()));
  }
  const readXlsx = (await import("read-excel-file/browser")).default;
  const rows = (await readXlsx(file)) as unknown as unknown[][];
  return rows.map((r) => r.map((c) => (c == null ? "" : String(c).trim())));
}

/** Tombol impor Excel/CSV kecil untuk mengisi editor tahap dari berkas. */
function ExcelImportInline({ hint, onRows }: { hint: string; onRows: (rows: string[][]) => void }) {
  return (
    <label
      className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-dashed border-brand-300 bg-brand-50/40 px-2.5 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-50"
      title={hint}
    >
      ⬆ Import Excel
      <input
        type="file"
        accept=".xlsx,.xls,.csv"
        className="hidden"
        onChange={async (e) => {
          const f = e.target.files?.[0];
          if (!f) return;
          try {
            const rows = await parseSpreadsheet(f);
            onRows(rows);
          } finally {
            e.target.value = "";
          }
        }}
      />
    </label>
  );
}

/** Buang baris header bila sel pertama memuat kata kunci umum. */
function stripHeader(rows: string[][], keywords: string[]): string[][] {
  if (rows.length === 0) return rows;
  const first = (rows[0][0] ?? "").toLowerCase();
  const isHeader = keywords.some((k) => first.includes(k));
  return isHeader ? rows.slice(1) : rows;
}

type CplOpt = { kode: string; deskripsi?: string };

const KERANGKA_LABEL: Record<string, string> = {
  bloom_anderson: "Kognitif (Bloom)",
  krathwohl: "Afektif (Krathwohl)",
  dave: "Psikomotor (Dave)",
  simpson: "Psikomotor (Simpson)",
};

/** Pemilih BANYAK level taksonomi (chip + dropdown berkelompok). Fallback ke input teks. */
function TaksonomiPicker({
  selected,
  options,
  onChange,
}: {
  selected: string[];
  options: Taksonomi[];
  onChange: (v: string[]) => void;
}) {
  if (options.length === 0) {
    return (
      <input
        className={inputCls}
        value={selected.join(", ")}
        placeholder="mis. C4, A3"
        onChange={(e) =>
          onChange(e.target.value.split(/[;,]/).map((s) => s.trim()).filter(Boolean))
        }
      />
    );
  }
  const known = new Set(options.map((o) => o.kode));
  const groups = options.reduce<Record<string, Taksonomi[]>>((acc, t) => {
    (acc[t.kerangka] ??= []).push(t);
    return acc;
  }, {});
  const available = options.filter((o) => !selected.includes(o.kode));
  const namaOf = (kode: string) => options.find((o) => o.kode === kode)?.nama;

  return (
    <div>
      <div className="flex flex-wrap items-center gap-1">
        {selected.length === 0 && <span className="text-[11px] text-gray-400">Belum ada level</span>}
        {selected.map((k) => {
          const ok = known.has(k);
          return (
            <span
              key={k}
              className={`inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-medium ${
                ok ? "bg-amber-50 text-amber-700 ring-1 ring-amber-200" : "bg-rose-50 text-rose-700 ring-1 ring-rose-200"
              }`}
              title={namaOf(k) ?? "tak dikenal"}
            >
              {!ok && "⚠ "}
              {k}
              <button
                type="button"
                onClick={() => onChange(selected.filter((x) => x !== k))}
                className="hover:text-rose-600"
                title="Hapus"
              >
                ×
              </button>
            </span>
          );
        })}
      </div>
      {available.length > 0 && (
        <select
          className={`${inputCls} mt-1`}
          value=""
          onChange={(e) => {
            if (e.target.value) onChange([...selected, e.target.value]);
          }}
        >
          <option value="">+ Tambah level…</option>
          {Object.entries(groups).map(([k, items]) => {
            const avail = items.filter((t) => !selected.includes(t.kode));
            if (avail.length === 0) return null;
            return (
              <optgroup key={k} label={KERANGKA_LABEL[k] ?? k}>
                {avail.map((t) => (
                  <option key={t.id} value={t.kode}>
                    {t.kode} — {t.nama}
                  </option>
                ))}
              </optgroup>
            );
          })}
        </select>
      )}
    </div>
  );
}

/** Pemilih CPL: chip terpilih (bisa dihapus) + dropdown + peringatan kode tak dikenal. */
function CplPicker({
  selected,
  options,
  onChange,
}: {
  selected: string[];
  options: CplOpt[];
  onChange: (v: string[]) => void;
}) {
  const known = new Set(options.map((o) => o.kode));
  const available = options.filter((o) => !selected.includes(o.kode));
  const invalid = selected.filter((k) => !known.has(k));

  const suggest = (bad: string): string => {
    let best = "";
    let bestScore = -1;
    const a = bad.toUpperCase();
    for (const o of options) {
      const b = o.kode.toUpperCase();
      let s = 0;
      while (s < a.length && s < b.length && a[s] === b[s]) s++;
      if (s > bestScore) {
        bestScore = s;
        best = o.kode;
      }
    }
    return best;
  };

  return (
    <div>
      <span className={labelCls}>CPL yang diampu</span>
      <div className="mt-1 flex flex-wrap items-center gap-1.5">
        {selected.length === 0 && <span className="text-[11px] text-gray-400">Belum ada CPL dipilih</span>}
        {selected.map((k) => {
          const ok = known.has(k);
          return (
            <span
              key={k}
              className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium ${
                ok ? "bg-brand-50 text-brand-700" : "bg-rose-50 text-rose-700 ring-1 ring-rose-200"
              }`}
            >
              {!ok && "⚠ "}
              {k}
              <button
                type="button"
                onClick={() => onChange(selected.filter((x) => x !== k))}
                className="hover:text-rose-600"
                title="Hapus"
              >
                ×
              </button>
            </span>
          );
        })}
      </div>
      {available.length > 0 && (
        <select
          className={`${inputCls} mt-1.5`}
          value=""
          onChange={(e) => {
            if (e.target.value) onChange([...selected, e.target.value]);
          }}
        >
          <option value="">— Pilih CPL —</option>
          {available.map((o) => (
            <option key={o.kode} value={o.kode}>
              {o.kode}
              {o.deskripsi ? ` — ${o.deskripsi.slice(0, 60)}` : ""}
            </option>
          ))}
        </select>
      )}
      {invalid.map((bad) => (
        <p key={bad} className="mt-1 text-[11px] text-rose-600">
          Kode <b>{bad}</b> tidak ada di kurikulum ini.
          {options.length > 0 && (
            <>
              {" "}Rekomendasi:{" "}
              <button
                type="button"
                className="font-semibold underline"
                onClick={() => onChange(selected.map((x) => (x === bad ? suggest(bad) : x)))}
              >
                {suggest(bad)}
              </button>
            </>
          )}
        </p>
      ))}
    </div>
  );
}

export function CpmkEditor({
  value,
  onChange,
  cplList = [],
  taksonomiList = [],
}: {
  value: CpmkItem[];
  onChange: (v: CpmkItem[]) => void;
  cplList?: CplOpt[];
  taksonomiList?: Taksonomi[];
}) {
  const set = (i: number, patch: Partial<CpmkItem>) =>
    onChange(value.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));

  const importRows = (rows: string[][]) => {
    const data = stripHeader(rows, ["kode", "cpmk"]);
    const parsed: CpmkItem[] = data
      .filter((r) => (r[0] ?? "").trim())
      .map((r, idx) => ({
        kode: (r[0] ?? "").trim() || `CPMK${value.length + idx + 1}`,
        cpl_kode: (r[1] ?? "")
          .split(/[;,]/)
          .map((s) => s.trim())
          .filter(Boolean),
        taksonomi_kode: (r[2] ?? "")
          .split(/[;,]/)
          .map((s) => s.trim())
          .filter(Boolean),
        deskripsi: (r[3] ?? "").trim(),
      }));
    if (parsed.length > 0) onChange([...value, ...parsed]);
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-[11px] text-muted">Isi manual, gunakan dropdown CPL, atau impor massal.</p>
        <ExcelImportInline hint="Kolom: Kode | CPL (pisah ; atau ,) | Taksonomi (pisah ; atau ,) | Deskripsi" onRows={importRows} />
      </div>
      {value.map((c, i) => (
        <RowShell key={i} onRemove={() => onChange(value.filter((_, idx) => idx !== i))}>
          <div className="grid gap-2 sm:grid-cols-4">
            <label className="sm:col-span-1">
              <span className={labelCls}>Kode</span>
              <input className={inputCls} value={c.kode} onChange={(e) => set(i, { kode: e.target.value })} />
            </label>
            <div className="sm:col-span-2">
              <CplPicker
                selected={c.cpl_kode ?? []}
                options={cplList}
                onChange={(v) => set(i, { cpl_kode: v })}
              />
            </div>
            <div className="sm:col-span-1">
              <span className={labelCls}>Taksonomi (bisa &gt;1)</span>
              <TaksonomiPicker
                selected={c.taksonomi_kode ?? []}
                options={taksonomiList}
                onChange={(v) => set(i, { taksonomi_kode: v })}
              />
            </div>
          </div>
          <label className="mt-2 block">
            <span className={labelCls}>Deskripsi</span>
            <textarea
              className={inputCls}
              rows={2}
              value={c.deskripsi}
              onChange={(e) => set(i, { deskripsi: e.target.value })}
            />
          </label>
        </RowShell>
      ))}
      <button
        type="button"
        onClick={() => onChange([...value, { kode: `CPMK${value.length + 1}`, deskripsi: "", cpl_kode: [] }])}
        className={buttonClass("ghost", "sm")}
      >
        + Tambah CPMK
      </button>
    </div>
  );
}

export function SubCpmkEditor({
  value,
  onChange,
  cpmkList = [],
  taksonomiList = [],
}: {
  value: SubCpmkItem[];
  onChange: (v: SubCpmkItem[]) => void;
  cpmkList?: string[];
  taksonomiList?: Taksonomi[];
}) {
  const set = (i: number, patch: Partial<SubCpmkItem>) =>
    onChange(value.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));

  const importRows = (rows: string[][]) => {
    const data = stripHeader(rows, ["kode", "sub"]);
    const parsed: SubCpmkItem[] = data
      .filter((r) => (r[0] ?? "").trim())
      .map((r, idx) => ({
        kode: (r[0] ?? "").trim() || `Sub-CPMK${value.length + idx + 1}`,
        cpmk_kode: (r[1] ?? "").trim() || undefined,
        taksonomi_kode: (r[2] ?? "")
          .split(/[;,]/)
          .map((s) => s.trim())
          .filter(Boolean),
        deskripsi: (r[3] ?? "").trim(),
        indikator: (r[4] ?? "")
          .split(/[;\n]/)
          .map((s) => s.trim())
          .filter(Boolean),
      }));
    if (parsed.length > 0) onChange([...value, ...parsed]);
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-[11px] text-muted">Isi manual, pilih CPMK induk, atau impor massal.</p>
        <ExcelImportInline
          hint="Kolom: Kode | CPMK Induk | Taksonomi | Deskripsi | Indikator (pisah ;)"
          onRows={importRows}
        />
      </div>
      {value.map((s, i) => (
        <RowShell key={i} onRemove={() => onChange(value.filter((_, idx) => idx !== i))}>
          <div className="grid gap-2 sm:grid-cols-3">
            <label>
              <span className={labelCls}>Kode</span>
              <input className={inputCls} value={s.kode} onChange={(e) => set(i, { kode: e.target.value })} />
            </label>
            <label>
              <span className={labelCls}>CPMK Induk</span>
              {cpmkList.length > 0 ? (
                <select
                  className={inputCls}
                  value={s.cpmk_kode ?? ""}
                  onChange={(e) => set(i, { cpmk_kode: e.target.value })}
                >
                  <option value="">— Pilih CPMK —</option>
                  {cpmkList.map((k) => (
                    <option key={k} value={k}>
                      {k}
                    </option>
                  ))}
                  {s.cpmk_kode && !cpmkList.includes(s.cpmk_kode) && (
                    <option value={s.cpmk_kode}>⚠ {s.cpmk_kode} (tak dikenal)</option>
                  )}
                </select>
              ) : (
                <input
                  className={inputCls}
                  value={s.cpmk_kode ?? ""}
                  onChange={(e) => set(i, { cpmk_kode: e.target.value })}
                />
              )}
            </label>
            <div>
              <span className={labelCls}>Taksonomi (bisa &gt;1)</span>
              <TaksonomiPicker
                selected={s.taksonomi_kode ?? []}
                options={taksonomiList}
                onChange={(v) => set(i, { taksonomi_kode: v })}
              />
            </div>
          </div>
          <label className="mt-2 block">
            <span className={labelCls}>Deskripsi</span>
            <textarea
              className={inputCls}
              rows={2}
              value={s.deskripsi}
              onChange={(e) => set(i, { deskripsi: e.target.value })}
            />
          </label>
          <label className="mt-2 block">
            <span className={labelCls}>Indikator (satu per baris)</span>
            <textarea
              className={inputCls}
              rows={2}
              value={(s.indikator ?? []).join("\n")}
              onChange={(e) =>
                set(i, { indikator: e.target.value.split("\n").map((x) => x.trim()).filter(Boolean) })
              }
            />
          </label>
        </RowShell>
      ))}
      <button
        type="button"
        onClick={() =>
          onChange([...value, { kode: `Sub-CPMK${value.length + 1}`, deskripsi: "", cpmk_kode: "", indikator: [] }])
        }
        className={buttonClass("ghost", "sm")}
      >
        + Tambah Sub-CPMK
      </button>
    </div>
  );
}

type SubCpmkOption = {
  kode: string;
  deskripsi?: string;
  cpmk_kode?: string;
  cpmk_deskripsi?: string;
};

/** Dropdown Sub-CPMK (fallback ke input teks bila daftar kosong). */
function SubCpmkSelect({
  value,
  options,
  onChange,
}: {
  value: string;
  options: SubCpmkOption[];
  onChange: (v: string) => void;
}) {
  if (options.length === 0) {
    return <input className={inputCls} value={value} onChange={(e) => onChange(e.target.value)} />;
  }
  return (
    <select className={inputCls} value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">— Pilih Sub-CPMK —</option>
      {options.map((k) => (
        <option key={k.kode} value={k.kode}>
          {k.kode}{k.deskripsi ? ` — ${k.deskripsi}` : ""}
        </option>
      ))}
      {value && !options.some((o) => o.kode === value) && <option value={value}>⚠ {value} (tak dikenal)</option>}
    </select>
  );
}

export function MingguEditor({
  value,
  onChange,
  subCpmkList = [],
  estimasiWaktu = "",
}: {
  value: MingguItem[];
  onChange: (v: MingguItem[]) => void;
  subCpmkList?: SubCpmkOption[];
  estimasiWaktu?: string;
}) {
  const set = (i: number, patch: Partial<MingguItem>) =>
    onChange(value.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));
  const duplicateAt = (i: number) => {
    const row = value[i] ?? { minggu_ke: 1 };
    onChange([
      ...value.slice(0, i + 1),
      { ...row, sub_cpmk_kode: undefined },
      ...value.slice(i + 1),
    ]);
  };

  return (
    <div className="space-y-3">
      <div className="rounded-lg border border-brand-100 bg-brand-50/40 px-3 py-2 text-[11px] text-brand-900/80">
        <p className="font-semibold">Struktur form mengikuti 8 kolom Format RPS KPT 2024:</p>
        <p className="mt-0.5">(1) Mg · (2) Sub-CPMK · (3) Indikator · (4) Kriteria &amp; Bentuk Penilaian · (5) Bentuk Pembelajaran — Luring · (6) Bentuk Pembelajaran — Daring · (7) Materi Pembelajaran [Pustaka] · (8) Bobot (%).</p>
        <p className="mt-0.5 text-brand-900/60">Kriteria &amp; Bentuk Penilaian: tulis dua baris — <code>Kriteria: …</code> lalu baris baru <code>Teknik: …</code>. Materi Pembelajaran: pilih dari Bahan Kajian MK &amp; kutip Pustaka. Estimasi waktu dihitung otomatis dari SKS.</p>
      </div>
      <p className="text-[11px] text-muted">
        Untuk UTS/UAS yang menguji beberapa Sub-CPMK, duplikasi baris pada minggu yang sama lalu pilih Sub-CPMK berbeda di tiap baris.
      </p>
      {value.map((m, i) => {
        const selectedSub = subCpmkList.find((x) => x.kode === (m.sub_cpmk_kode ?? ""));
        const cpmkInfo = selectedSub?.cpmk_kode
          ? `CPMK ${selectedSub.cpmk_kode}${selectedSub.cpmk_deskripsi ? `: ${selectedSub.cpmk_deskripsi}` : ""}`
          : "";

        return (
          <RowShell key={i} onRemove={() => onChange(value.filter((_, idx) => idx !== i))}>
            {/* Kolom (1) Mg + (2) Sub-CPMK + (8) Bobot */}
            <div className="grid gap-2 sm:grid-cols-4">
              <label>
                <span className={labelCls}>(1) Minggu ke-</span>
                <input
                  type="number"
                  className={inputCls}
                  value={m.minggu_ke}
                  onChange={(e) => set(i, { minggu_ke: Number(e.target.value) })}
                />
              </label>
              <div className="sm:col-span-2">
                <span className={labelCls}>(2) Sub-CPMK (kemampuan akhir)</span>
                <SubCpmkSelect
                  value={m.sub_cpmk_kode ?? ""}
                  options={subCpmkList}
                  onChange={(v) => set(i, { sub_cpmk_kode: v })}
                />
                {selectedSub && (
                  <p className="mt-1 text-[10px] text-muted">
                    {selectedSub.deskripsi ?? "Deskripsi Sub-CPMK belum diisi"}
                    {cpmkInfo ? ` · ${cpmkInfo}` : ""}
                  </p>
                )}
              </div>
              <label>
                <span className={labelCls}>(8) Bobot Penilaian (%)</span>
                <input
                  type="number"
                  className={inputCls}
                  value={m.bobot_penilaian ?? ""}
                  onChange={(e) =>
                    set(i, { bobot_penilaian: e.target.value === "" ? undefined : Number(e.target.value) })
                  }
                />
              </label>
            </div>
            <div className="mt-1 flex justify-end">
              <button
                type="button"
                onClick={() => duplicateAt(i)}
                className="text-[11px] font-medium text-brand-700 hover:underline"
                title="Tambahkan Sub-CPMK lain di minggu yang sama"
              >
                + Sub-CPMK lain (minggu sama)
              </button>
            </div>

            {/* Kolom (3) Indikator + (4) Kriteria & Bentuk Penilaian */}
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
              <label>
                <span className={labelCls}>(3) Penilaian — Indikator</span>
                <textarea
                  className={inputCls}
                  rows={2}
                  value={m.indikator ?? ""}
                  onChange={(e) => set(i, { indikator: e.target.value })}
                />
              </label>
              <label>
                <span className={labelCls}>(4) Kriteria &amp; Bentuk Penilaian</span>
                <textarea
                  className={inputCls}
                  rows={2}
                  value={m.kriteria_penilaian ?? ""}
                  placeholder={"Kriteria: ketepatan analisis …\nTeknik: tes tertulis uraian."}
                  onChange={(e) => set(i, { kriteria_penilaian: e.target.value })}
                />
                <span className="mt-0.5 block text-[10px] text-muted">Dua baris: Kriteria dulu, lalu Teknik.</span>
              </label>
            </div>

            {/* Kolom (5) Luring + (6) Daring — masing-masing berisi bentuk + pendukung */}
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
              <div className="rounded-md border border-border/70 bg-gray-50/40 p-2">
                <span className={labelCls}>(5) Bentuk Pembelajaran — Luring</span>
                <input
                  className={inputCls}
                  value={m.bentuk_luring ?? ""}
                  placeholder="mis. tatap muka di kelas/lab, praktikum"
                  onChange={(e) => set(i, { bentuk_luring: e.target.value })}
                />
                <label className="mt-2 block">
                  <span className={labelCls}>Metode Pembelajaran</span>
                  <input
                    className={inputCls}
                    value={m.metode_pembelajaran ?? ""}
                    placeholder="Discovery Learning / Case Method / PBL / dll"
                    onChange={(e) => set(i, { metode_pembelajaran: e.target.value })}
                  />
                </label>
                <div className="mt-2">
                  <span className={labelCls}>Estimasi Waktu</span>
                  <div className={`${inputCls} bg-gray-100 text-muted`} title="Dihitung otomatis dari SKS (KPT/SN-Dikti) — tidak dapat diubah manual">
                    {estimasiWaktu || "— (tentukan SKS mata kuliah)"}
                  </div>
                  <span className="mt-0.5 block text-[10px] text-muted">Otomatis dari SKS (deterministik).</span>
                </div>
              </div>
              <div className="rounded-md border border-border/70 bg-gray-50/40 p-2">
                <span className={labelCls}>(6) Bentuk Pembelajaran — Daring</span>
                <input
                  className={inputCls}
                  value={m.bentuk_daring ?? ""}
                  placeholder="LMS/sinkronus/asinkronus"
                  onChange={(e) => set(i, { bentuk_daring: e.target.value })}
                />
                <label className="mt-2 block">
                  <span className={labelCls}>Penugasan / Pengalaman Belajar</span>
                  <textarea
                    className={inputCls}
                    rows={4}
                    value={m.pengalaman_belajar ?? ""}
                    onChange={(e) => set(i, { pengalaman_belajar: e.target.value })}
                  />
                </label>
              </div>
            </div>

            {/* Kolom (7) Materi Pembelajaran */}
            <label className="mt-2 block">
              <span className={labelCls}>(7) Materi Pembelajaran [Pustaka]</span>
              <textarea
                className={inputCls}
                rows={2}
                value={m.materi_pustaka ?? ""}
                placeholder={"Nama Bahan Kajian — ringkasan materi minggu ini [Pustaka: 1,3]"}
                onChange={(e) => set(i, { materi_pustaka: e.target.value })}
              />
              <span className="mt-0.5 block text-[10px] text-muted">Pilih dari Bahan Kajian MK &amp; sitir Pustaka Utama/Pendukung.</span>
            </label>
          </RowShell>
        );
      })}
      <button
        type="button"
        onClick={() => onChange([...value, { minggu_ke: value.length + 1 }])}
        className={buttonClass("ghost", "sm")}
      >
        + Tambah Minggu
      </button>
    </div>
  );
}

export function KomponenEditor({
  value,
  onChange,
  subCpmkList = [],
  mingguList = [],
}: {
  value: KomponenItem[];
  onChange: (v: KomponenItem[]) => void;
  subCpmkList?: SubCpmkOption[];
  mingguList?: number[];
}) {
  const set = (i: number, patch: Partial<KomponenItem>) =>
    onChange(value.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));
  const total = value.reduce((a, c) => a + (Number(c.bobot_persen) || 0), 0);
  return (
    <div className="space-y-3">
      {value.map((k, i) => {
        const selectedSub = subCpmkList.find((x) => x.kode === (k.sub_cpmk_kode ?? ""));
        return (
        <RowShell key={i} onRemove={() => onChange(value.filter((_, idx) => idx !== i))}>
          <div className="grid gap-2 sm:grid-cols-6">
            <label className="sm:col-span-2">
              <span className={labelCls}>Nama</span>
              <input className={inputCls} value={k.nama} onChange={(e) => set(i, { nama: e.target.value })} />
            </label>
            <label>
              <span className={labelCls}>Jenis</span>
              <input
                className={inputCls}
                value={k.jenis ?? ""}
                placeholder="tugas/uts/uas"
                onChange={(e) => set(i, { jenis: e.target.value })}
              />
            </label>
            <div>
              <span className={labelCls}>Sub-CPMK</span>
              <SubCpmkSelect
                value={k.sub_cpmk_kode ?? ""}
                options={subCpmkList}
                onChange={(v) => set(i, { sub_cpmk_kode: v })}
              />
              {selectedSub?.deskripsi && (
                <p className="mt-1 text-[10px] text-muted">{selectedSub.deskripsi}</p>
              )}
            </div>
            <label>
              <span className={labelCls}>Minggu</span>
              {mingguList.length > 0 ? (
                <select
                  className={inputCls}
                  value={k.minggu_ke ?? ""}
                  onChange={(e) =>
                    set(i, { minggu_ke: e.target.value === "" ? undefined : Number(e.target.value) })
                  }
                >
                  <option value="">—</option>
                  {mingguList.map((mg) => (
                    <option key={mg} value={mg}>
                      {mg}
                    </option>
                  ))}
                </select>
              ) : (
                <input
                  type="number"
                  className={inputCls}
                  value={k.minggu_ke ?? ""}
                  onChange={(e) =>
                    set(i, { minggu_ke: e.target.value === "" ? undefined : Number(e.target.value) })
                  }
                />
              )}
            </label>
            <label>
              <span className={labelCls}>Bobot %</span>
              <input
                type="number"
                className={inputCls}
                value={k.bobot_persen ?? 0}
                onChange={(e) => set(i, { bobot_persen: Number(e.target.value) })}
              />
            </label>
          </div>
          <label className="mt-2 block">
            <span className={labelCls}>Instrumen</span>
            <input
              className={inputCls}
              value={k.instrumen ?? ""}
              placeholder="mis. lembar tugas / soal esai / lembar observasi"
              onChange={(e) => set(i, { instrumen: e.target.value })}
            />
          </label>
          {k.rubrik && (k.rubrik.kriteria?.length ?? 0) > 0 && (
            <p className="mt-1 text-xs text-muted">
              Rubrik {k.rubrik.jenis ?? "analitik"}: {k.rubrik.kriteria!.length} kriteria (tersimpan otomatis saat commit).
            </p>
          )}
        </RowShell>
        );
      })}
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={() => onChange([...value, { nama: "", jenis: "tugas", bobot_persen: 0 }])}
          className={buttonClass("ghost", "sm")}
        >
          + Tambah Komponen
        </button>
        <span className={`text-xs font-semibold ${total === 100 ? "text-emerald-600" : "text-amber-600"}`}>
          Total bobot: {total}%
        </span>
      </div>
    </div>
  );
}
