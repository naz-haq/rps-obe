"use client";

import { useEffect, useRef, useState } from "react";
import { buttonClass } from "@/components/ui";
import type { MataKuliah } from "@/lib/api";
import { listReferensi, suggestReferensi } from "./actions";

type Row = { tipe: "utama" | "pendukung"; sitasi: string; draft?: boolean };

/**
 * Editor Pustaka/Referensi per Mata Kuliah di dalam modal MK. Menulis state ke
 * hidden input `referensi_json` agar ikut terkirim saat form MK disimpan.
 * Tombol "Saran AI" mengisi draf yang WAJIB diverifikasi dosen.
 */
export function ReferensiEditor({ mk }: { mk?: MataKuliah }) {
  const rootRef = useRef<HTMLDivElement>(null);
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState<boolean>(!!mk?.kode_mk);
  const [busyAi, setBusyAi] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [adaDraft, setAdaDraft] = useState(false);

  // Muat referensi yang sudah ada saat modal Edit dibuka.
  useEffect(() => {
    let aktif = true;
    if (mk?.kode_mk) {
      listReferensi(mk.institusi_id, mk.kode_mk).then((data) => {
        if (!aktif) return;
        setRows(data.map((r) => ({ tipe: r.tipe, sitasi: r.sitasi })));
        setLoading(false);
      });
    }
    return () => {
      aktif = false;
    };
  }, [mk?.kode_mk, mk?.institusi_id]);

  const payload = JSON.stringify(rows.map(({ tipe, sitasi }) => ({ tipe, sitasi })));

  const setRow = (i: number, patch: Partial<Row>) =>
    setRows((rs) => rs.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));
  const addRow = (tipe: "utama" | "pendukung") => setRows((rs) => [...rs, { tipe, sitasi: "" }]);
  const removeRow = (i: number) => setRows((rs) => rs.filter((_, idx) => idx !== i));

  const saranAi = async () => {
    setError(null);
    const form = rootRef.current?.closest("form");
    const val = (n: string) =>
      ((form?.elements.namedItem(n) as HTMLInputElement | HTMLSelectElement | null)?.value ?? "").trim();
    const nama = val("nama");
    if (!nama) {
      setError("Isi Nama mata kuliah dulu agar AI dapat menyarankan pustaka.");
      return;
    }
    setBusyAi(true);
    try {
      const sksTeori = Number(val("sks_teori")) || 0;
      const sksPraktik = Number(val("sks_praktik")) || 0;
      const res = await suggestReferensi({
        nama,
        jenis: val("jenis_mk") || undefined,
        sks: sksTeori + sksPraktik || null,
        deskripsi: val("deskripsi_singkat") || undefined,
        kode_mk: val("kode_mk") || undefined,
      });
      if (res.ok && Array.isArray(res.data)) {
        const saran = res.data.map((r) => ({ tipe: r.tipe, sitasi: r.sitasi, draft: true }));
        setRows((rs) => [...rs, ...saran]);
        if (saran.length > 0) setAdaDraft(true);
      } else {
        setError(res.message ?? "Layanan AI tidak tersedia saat ini.");
      }
    } catch {
      setError("Gagal menghubungi layanan AI.");
    } finally {
      setBusyAi(false);
    }
  };

  return (
    <div ref={rootRef} className="rounded-lg border border-border bg-gray-50/60 p-3">
      <input type="hidden" name="referensi_json" value={payload} />
      <div className="mb-2 flex items-center justify-between">
        <div>
          <span className="text-xs font-semibold text-ink">Pustaka / Referensi</span>
          <p className="text-[11px] text-muted">Rujukan &quot;Pustaka Utama &amp; Pendukung&quot; RPS + grounding saat generate.</p>
        </div>
        <button
          type="button"
          onClick={saranAi}
          disabled={busyAi}
          className="inline-flex items-center gap-1 rounded-md border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100 disabled:opacity-50"
        >
          {busyAi ? "Meminta…" : "✨ Saran AI"}
        </button>
      </div>

      {error && <p className="mb-2 text-xs text-red-600">{error}</p>}
      {adaDraft && (
        <p className="mb-2 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] text-amber-800">
          ⚠ Item bertanda <b>draf AI</b> berpotensi keliru (judul/penulis/tahun). <b>Verifikasi</b> sebelum menyimpan.
        </p>
      )}

      {loading ? (
        <p className="py-2 text-xs text-muted">Memuat referensi…</p>
      ) : rows.length === 0 ? (
        <p className="py-2 text-xs text-muted">Belum ada referensi. Tambah manual atau minta Saran AI.</p>
      ) : (
        <ul className="space-y-2">
          {rows.map((r, i) => (
            <li key={i} className="flex items-start gap-2">
              <select
                value={r.tipe}
                onChange={(e) => setRow(i, { tipe: e.target.value as "utama" | "pendukung" })}
                className="mt-0.5 rounded-md border border-border bg-surface px-1.5 py-1 text-xs text-ink outline-none focus-ring"
              >
                <option value="utama">Utama</option>
                <option value="pendukung">Pendukung</option>
              </select>
              <div className="flex-1">
                <input
                  value={r.sitasi}
                  onChange={(e) => setRow(i, { sitasi: e.target.value, draft: false })}
                  placeholder="Penulis (Tahun). Judul. Penerbit."
                  className={`w-full rounded-md border px-2 py-1 text-xs text-ink outline-none focus-ring ${
                    r.draft ? "border-amber-300 bg-amber-50/50" : "border-border bg-surface"
                  }`}
                />
                {r.draft && <span className="mt-0.5 block text-[10px] text-amber-700">draf AI — verifikasi</span>}
              </div>
              <button
                type="button"
                onClick={() => removeRow(i)}
                className="mt-0.5 rounded-md px-1.5 py-1 text-xs text-red-600 hover:bg-red-50"
                title="Hapus"
              >
                ✕
              </button>
            </li>
          ))}
        </ul>
      )}

      <div className="mt-2 flex gap-2">
        <button type="button" onClick={() => addRow("utama")} className={buttonClass("secondary", "sm")}>
          + Pustaka Utama
        </button>
        <button type="button" onClick={() => addRow("pendukung")} className={buttonClass("secondary", "sm")}>
          + Pustaka Pendukung
        </button>
      </div>
    </div>
  );
}
