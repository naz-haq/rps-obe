"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Modal } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { EstimasiWaktu, RincianPertemuan, TahapSkenario } from "@/lib/api";
import { simpanRincianPertemuan } from "../actions";

/**
 * Editor MANUAL rincian pertemuan satu pekan — alternatif jalur AI.
 * Mendukung dua bentuk: skenario tahapan (MK reguler) dan sesi per pertemuan
 * (MK blok/profesi); bentuk bisa dialihkan bila dosen ingin menyusun sendiri.
 */

type TahapDraft = { tahap: string; kegiatan: string; durasi: string };
type SesiDraft = { topik: string; aktivitas: string; metode: string; durasi: string };

const TEMPLATE_TAHAPAN: TahapDraft[] = [
  { tahap: "Pendahuluan", kegiatan: "", durasi: "" },
  { tahap: "Kegiatan Inti", kegiatan: "", durasi: "" },
  { tahap: "Penutup", kegiatan: "", durasi: "" },
];

function keTahapDraft(t: TahapSkenario): TahapDraft {
  return { tahap: t.tahap ?? "", kegiatan: t.kegiatan ?? "", durasi: t.durasi_menit ? String(t.durasi_menit) : "" };
}

function angka(v: string): number | null {
  const n = parseInt(v, 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

export function EditRincianButton({
  rpsId,
  mingguKe,
  rincian,
  estimasi,
}: {
  rpsId: number;
  mingguKe: number;
  rincian: RincianPertemuan[];
  estimasi: EstimasiWaktu | null;
}) {
  const router = useRouter();
  const adaTahapan = rincian.some((p) => (p.tahapan?.length ?? 0) > 0);
  const defaultSkenario = rincian.length > 0 ? adaTahapan : (estimasi?.jumlah_pertemuan ?? 1) <= 1;
  const kontak = (estimasi?.tm_menit ?? 0) + (estimasi?.praktik_menit ?? 0) || (estimasi?.total_menit ?? 0);

  const [modeSkenario, setModeSkenario] = useState(defaultSkenario);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // --- draf skenario (satu pertemuan/pekan) ---
  const s0 = adaTahapan ? rincian[0] : undefined;
  const [topik, setTopik] = useState(s0?.topik ?? "");
  const [tahapan, setTahapan] = useState<TahapDraft[]>(
    s0?.tahapan?.length ? s0.tahapan.map(keTahapDraft) : TEMPLATE_TAHAPAN,
  );
  const [pt, setPt] = useState(s0?.penugasan_terstruktur ?? "");
  const [bm, setBm] = useState(s0?.belajar_mandiri ?? "");
  const [ptMenit, setPtMenit] = useState(String(s0?.pt_menit ?? estimasi?.pt_menit ?? "") || "");
  const [bmMenit, setBmMenit] = useState(String(s0?.bm_menit ?? estimasi?.bm_menit ?? "") || "");

  // --- draf pemecahan sesi ---
  const [sesi, setSesi] = useState<SesiDraft[]>(
    !adaTahapan && rincian.length > 0
      ? rincian.map((p) => ({
          topik: p.topik ?? "",
          aktivitas: p.aktivitas ?? "",
          metode: p.metode ?? "",
          durasi: p.durasi_menit ? String(p.durasi_menit) : "",
        }))
      : [{ topik: "", aktivitas: "", metode: "", durasi: "" }],
  );

  function ubahTahap(i: number, patch: Partial<TahapDraft>) {
    setTahapan((prev) => prev.map((t, j) => (j === i ? { ...t, ...patch } : t)));
  }
  function ubahSesi(i: number, patch: Partial<SesiDraft>) {
    setSesi((prev) => prev.map((s, j) => (j === i ? { ...s, ...patch } : s)));
  }

  async function simpan(close: () => void) {
    setPending(true);
    setError(null);
    const payload: Partial<RincianPertemuan>[] = modeSkenario
      ? [
          {
            topik: topik.trim() || null,
            durasi_menit: kontak > 0 ? kontak : null,
            tahapan: tahapan
              .filter((t) => t.kegiatan.trim() !== "")
              .map((t) => ({ tahap: t.tahap.trim() || null, kegiatan: t.kegiatan.trim(), durasi_menit: angka(t.durasi) })),
            penugasan_terstruktur: pt.trim() || null,
            belajar_mandiri: bm.trim() || null,
            pt_menit: angka(ptMenit),
            bm_menit: angka(bmMenit),
          },
        ]
      : sesi
          .filter((s) => s.topik.trim() !== "" || s.aktivitas.trim() !== "")
          .map((s) => ({
            topik: s.topik.trim() || null,
            aktivitas: s.aktivitas.trim() || null,
            metode: s.metode.trim() || null,
            durasi_menit: angka(s.durasi),
          }));

    const r = await simpanRincianPertemuan(rpsId, mingguKe, payload);
    setPending(false);
    if (r.ok) {
      close();
      router.refresh();
    } else {
      setError(r.message ?? "Gagal menyimpan rincian.");
    }
  }

  const inputCls =
    "w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400";

  return (
    <Modal trigger="Edit Manual" title={`Edit Rincian — Pekan ${mingguKe}`} triggerVariant="secondary" triggerSize="sm" size="lg">
      {(close) => (
        <div className="space-y-4">
          <div className="flex items-center gap-2 text-xs">
            <span className="font-medium text-ink">Bentuk:</span>
            <button
              type="button"
              onClick={() => setModeSkenario(true)}
              className={buttonClass(modeSkenario ? "primary" : "ghost", "sm")}
            >
              Skenario tahapan
            </button>
            <button
              type="button"
              onClick={() => setModeSkenario(false)}
              className={buttonClass(!modeSkenario ? "primary" : "ghost", "sm")}
            >
              Sesi per pertemuan
            </button>
          </div>

          {modeSkenario ? (
            <div className="space-y-3">
              <label className="block">
                <span className="mb-1 block text-xs font-medium text-ink">Topik pertemuan</span>
                <input value={topik} onChange={(e) => setTopik(e.target.value)} className={inputCls} placeholder="Topik pekan ini" />
              </label>
              <div>
                <div className="mb-1 flex items-center justify-between">
                  <span className="text-xs font-medium text-ink">
                    Tahapan{kontak > 0 ? ` (waktu kontak ~${kontak} menit)` : ""}
                  </span>
                  <button
                    type="button"
                    onClick={() => setTahapan((prev) => [...prev, { tahap: "", kegiatan: "", durasi: "" }])}
                    className={buttonClass("ghost", "sm")}
                  >
                    + Tahap
                  </button>
                </div>
                <div className="space-y-2">
                  {tahapan.map((t, i) => (
                    <div key={i} className="flex items-start gap-2">
                      <input
                        value={t.tahap}
                        onChange={(e) => ubahTahap(i, { tahap: e.target.value })}
                        className={`${inputCls} w-44 shrink-0`}
                        placeholder="Tahap"
                      />
                      <textarea
                        value={t.kegiatan}
                        onChange={(e) => ubahTahap(i, { kegiatan: e.target.value })}
                        rows={2}
                        className={inputCls}
                        placeholder="Kegiatan dosen–mahasiswa pada tahap ini"
                      />
                      <input
                        value={t.durasi}
                        onChange={(e) => ubahTahap(i, { durasi: e.target.value })}
                        className={`${inputCls} w-20 shrink-0`}
                        placeholder="menit"
                        inputMode="numeric"
                      />
                      <button
                        type="button"
                        onClick={() => setTahapan((prev) => prev.filter((_, j) => j !== i))}
                        className="mt-2 text-muted hover:text-red-600"
                        aria-label="Hapus tahap"
                      >
                        ✕
                      </button>
                    </div>
                  ))}
                </div>
              </div>
              <div className="grid grid-cols-[1fr_6rem] items-start gap-2">
                <label className="block">
                  <span className="mb-1 block text-xs font-medium text-ink">Penugasan Terstruktur</span>
                  <textarea value={pt} onChange={(e) => setPt(e.target.value)} rows={2} className={inputCls} placeholder="Tugas luar kelas pekan ini" />
                </label>
                <label className="block">
                  <span className="mb-1 block text-xs font-medium text-ink">± menit</span>
                  <input value={ptMenit} onChange={(e) => setPtMenit(e.target.value)} className={inputCls} inputMode="numeric" />
                </label>
              </div>
              <div className="grid grid-cols-[1fr_6rem] items-start gap-2">
                <label className="block">
                  <span className="mb-1 block text-xs font-medium text-ink">Belajar Mandiri</span>
                  <textarea value={bm} onChange={(e) => setBm(e.target.value)} rows={2} className={inputCls} placeholder="Bahan bacaan/persiapan pekan berikutnya" />
                </label>
                <label className="block">
                  <span className="mb-1 block text-xs font-medium text-ink">± menit</span>
                  <input value={bmMenit} onChange={(e) => setBmMenit(e.target.value)} className={inputCls} inputMode="numeric" />
                </label>
              </div>
            </div>
          ) : (
            <div>
              <div className="mb-1 flex items-center justify-between">
                <span className="text-xs font-medium text-ink">Sesi/pertemuan pekan ini</span>
                <button
                  type="button"
                  onClick={() => setSesi((prev) => [...prev, { topik: "", aktivitas: "", metode: "", durasi: "" }])}
                  className={buttonClass("ghost", "sm")}
                >
                  + Pertemuan
                </button>
              </div>
              <div className="space-y-3">
                {sesi.map((s, i) => (
                  <div key={i} className="rounded-lg border border-border p-3">
                    <div className="mb-2 flex items-center justify-between">
                      <span className="text-xs font-semibold text-ink">Pertemuan {i + 1}</span>
                      <button
                        type="button"
                        onClick={() => setSesi((prev) => prev.filter((_, j) => j !== i))}
                        className="text-muted hover:text-red-600"
                        aria-label="Hapus pertemuan"
                      >
                        ✕
                      </button>
                    </div>
                    <div className="space-y-2">
                      <input value={s.topik} onChange={(e) => ubahSesi(i, { topik: e.target.value })} className={inputCls} placeholder="Topik" />
                      <textarea
                        value={s.aktivitas}
                        onChange={(e) => ubahSesi(i, { aktivitas: e.target.value })}
                        rows={2}
                        className={inputCls}
                        placeholder="Aktivitas pembelajaran"
                      />
                      <div className="flex gap-2">
                        <input value={s.metode} onChange={(e) => ubahSesi(i, { metode: e.target.value })} className={inputCls} placeholder="Metode" />
                        <input
                          value={s.durasi}
                          onChange={(e) => ubahSesi(i, { durasi: e.target.value })}
                          className={`${inputCls} w-24 shrink-0`}
                          placeholder="menit"
                          inputMode="numeric"
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {error && <p className="text-xs text-red-600">{error}</p>}
          <div className="flex items-center justify-end gap-2 border-t border-border pt-3">
            <button type="button" onClick={close} className={buttonClass("ghost")}>
              Batal
            </button>
            <button type="button" disabled={pending} onClick={() => simpan(close)} className={buttonClass("primary")}>
              {pending ? "Menyimpan…" : "Simpan Rincian"}
            </button>
          </div>
        </div>
      )}
    </Modal>
  );
}
