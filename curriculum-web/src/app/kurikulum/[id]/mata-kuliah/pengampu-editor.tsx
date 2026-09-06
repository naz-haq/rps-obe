"use client";

import { useEffect, useState } from "react";
import { buttonClass } from "@/components/ui";
import type { MataKuliah } from "@/lib/api";
import { listPengampu } from "@/app/generator/actions";

type Row = { nidn: string; nama: string; peran: "koordinator" | "anggota" };

/**
 * Editor Dosen Pengampu per Mata Kuliah. Menulis state ke hidden input
 * `pengampu_json` agar ikut terkirim saat form Detail MK disimpan. Muncul di
 * header RPS cetak/DOCX. Nama + NIDN tersimpan ke master dosen (upsert).
 */
export function PengampuEditor({ mk }: { mk?: MataKuliah }) {
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState<boolean>(!!mk?.kode_mk);

  useEffect(() => {
    let aktif = true;
    if (mk?.kode_mk && mk?.institusi_id) {
      listPengampu(mk.kode_mk, mk.institusi_id).then((data) => {
        if (!aktif) return;
        setRows(data.map((r) => ({ nidn: r.nidn, nama: r.nama, peran: r.peran })));
        setLoading(false);
      });
    }
    return () => {
      aktif = false;
    };
  }, [mk?.kode_mk, mk?.institusi_id]);

  const payload = JSON.stringify(rows.map(({ nidn, nama, peran }) => ({ nidn, nama, peran })));

  const setRow = (i: number, patch: Partial<Row>) =>
    setRows((rs) => rs.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));
  const addRow = () => setRows((rs) => [...rs, { nidn: "", nama: "", peran: rs.length === 0 ? "koordinator" : "anggota" }]);
  const removeRow = (i: number) => setRows((rs) => rs.filter((_, idx) => idx !== i));

  return (
    <div className="rounded-lg border border-border bg-gray-50/60 p-3">
      <input type="hidden" name="pengampu_json" value={payload} />
      <div className="mb-2 flex items-center justify-between">
        <div>
          <p className="text-xs font-semibold text-ink">Dosen Pengampu</p>
          <p className="text-[11px] text-muted">Nama, NIDN, dan peran — tampil di header RPS cetak/DOCX.</p>
        </div>
        <button type="button" onClick={addRow} className={buttonClass("secondary", "xs")}>
          + Tambah Dosen
        </button>
      </div>

      {loading ? (
        <p className="text-xs text-muted">Memuat…</p>
      ) : rows.length === 0 ? (
        <p className="text-xs text-muted">Belum ada dosen pengampu. Klik “Tambah Dosen”.</p>
      ) : (
        <div className="space-y-2">
          {rows.map((r, i) => (
            <div key={i} className="flex flex-wrap items-center gap-2">
              <input
                value={r.nama}
                onChange={(e) => setRow(i, { nama: e.target.value })}
                placeholder="Nama dosen"
                className="min-w-[10rem] flex-1 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-sm outline-none focus-ring"
              />
              <input
                value={r.nidn}
                onChange={(e) => setRow(i, { nidn: e.target.value })}
                placeholder="NIDN"
                className="w-32 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-sm outline-none focus-ring"
              />
              <select
                value={r.peran}
                onChange={(e) => setRow(i, { peran: e.target.value === "koordinator" ? "koordinator" : "anggota" })}
                className="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-sm outline-none focus-ring"
              >
                <option value="koordinator">Koordinator</option>
                <option value="anggota">Anggota</option>
              </select>
              <button
                type="button"
                onClick={() => removeRow(i)}
                className="rounded-lg border border-border px-2 py-1.5 text-xs text-rose-600 hover:bg-rose-50"
                title="Hapus dosen"
              >
                Hapus
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
