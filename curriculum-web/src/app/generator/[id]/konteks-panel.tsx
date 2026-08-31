"use client";

import { useState } from "react";
import { buttonClass } from "@/components/ui";
import type { GenerateSession, KonteksTambahan } from "@/lib/api";
import { updateKonteks } from "../actions";

const FIELDS: { key: keyof KonteksTambahan; label: string; rows: number; placeholder: string }[] = [
  {
    key: "kompetensi_khusus",
    label: "Kompetensi Khusus MK",
    rows: 3,
    placeholder: "Kompetensi spesifik yang wajib tercermin pada CPMK/Sub-CPMK…",
  },
  {
    key: "bok",
    label: "Body of Knowledge / Cakupan Keilmuan",
    rows: 3,
    placeholder: "Peta & batas materi — topik mingguan tidak boleh keluar dari sini…",
  },
  {
    key: "bahan_kajian_khusus",
    label: "Bahan Kajian Khusus MK",
    rows: 2,
    placeholder: "Bahan kajian tambahan dari dosen — digabung dengan bahan kajian kurikulum…",
  },
];

/** Panel rujukan tambahan dosen: dibaca AI sebagai konteks otoritatif tiap generate. */
export function KonteksPanel({ session, committed }: { session: GenerateSession; committed: boolean }) {
  const k = session.konteks_tambahan ?? {};
  const terisi = FIELDS.filter((f) => (k[f.key] ?? "").trim() !== "");
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [pesan, setPesan] = useState<string | null>(null);
  const [vals, setVals] = useState<KonteksTambahan>({
    kompetensi_khusus: k.kompetensi_khusus ?? "",
    bok: k.bok ?? "",
    bahan_kajian_khusus: k.bahan_kajian_khusus ?? "",
  });

  const simpan = async () => {
    setBusy(true);
    setPesan(null);
    const res = await updateKonteks(session.id, vals);
    setPesan(res.ok ? "Tersimpan — dipakai pada generate berikutnya." : (res.message ?? "Gagal menyimpan."));
    if (res.ok) setOpen(false);
    setBusy(false);
  };

  return (
    <section className="rounded-xl border border-border bg-surface p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold text-ink">Rujukan Tambahan untuk AI</h3>
          <p className="text-xs text-muted">
            {terisi.length > 0
              ? `Terisi: ${terisi.map((f) => f.label).join(" · ")}`
              : "Belum diisi — AI hanya berpegang pada data MK, CPL, bahan kajian, pustaka, dan dokumen tertaut."}
          </p>
        </div>
        {!committed && (
          <button type="button" onClick={() => setOpen((v) => !v)} className={buttonClass("secondary", "sm")}>
            {open ? "Tutup" : terisi.length > 0 ? "Ubah" : "Isi Sekarang"}
          </button>
        )}
      </div>

      {pesan && <p className="mt-2 text-xs text-emerald-700">{pesan}</p>}

      {!open && terisi.length > 0 && (
        <dl className="mt-3 space-y-2">
          {terisi.map((f) => (
            <div key={f.key} className="rounded-lg bg-gray-50/60 px-3 py-2">
              <dt className="text-[11px] font-semibold text-muted">{f.label}</dt>
              <dd className="whitespace-pre-wrap text-xs text-ink">{(k[f.key] ?? "").trim()}</dd>
            </div>
          ))}
        </dl>
      )}

      {open && (
        <div className="mt-3 space-y-3">
          {FIELDS.map((f) => (
            <label key={f.key} className="block">
              <span className="mb-1 block text-xs font-medium text-ink">{f.label}</span>
              <textarea
                rows={f.rows}
                value={vals[f.key] ?? ""}
                placeholder={f.placeholder}
                onChange={(e) => setVals((v) => ({ ...v, [f.key]: e.target.value }))}
                className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
              />
            </label>
          ))}
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setOpen(false)} className={buttonClass("secondary", "sm")}>
              Batal
            </button>
            <button type="button" onClick={simpan} disabled={busy} className={buttonClass("primary", "sm")}>
              {busy ? "Menyimpan…" : "Simpan"}
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
