"use client";

import type { Cpl } from "@/lib/api";
import {
  type Draf,
  getCpmk,
  getSubCpmk,
  getKomponen,
  maxTaksonomiLevel,
} from "./draft";

type Check = { label: string; ok: boolean | null; detail: string };

export function SelfCheck({ draf, cplList }: { draf: Draf; cplList: Cpl[] }) {
  const cpmk = getCpmk(draf);
  const sub = getSubCpmk(draf);
  const komponen = getKomponen(draf);

  const cplTerbengkalai = cplList.filter(
    (c) => !cpmk.some((k) => (k.cpl_kode ?? []).includes(c.kode)),
  );
  const cpmkYatim = cpmk.filter((k) => (k.cpl_kode ?? []).length === 0);
  const cpmkTanpaSub = cpmk.filter(
    (k) => !sub.some((s) => s.cpmk_kode === k.kode),
  );
  const totalBobot = komponen.reduce((a, c) => a + (Number(c.bobot_persen) || 0), 0);

  const taksonomiWarnings: { kode: string; pesan: string }[] = [];
  cpmk.forEach((k) => {
    const subs = sub.filter((s) => s.cpmk_kode === k.kode);
    const parent = maxTaksonomiLevel(k.taksonomi_kode);
    if (subs.length > 0 && parent > 0) {
      const maxChild = Math.max(0, ...subs.map((s) => maxTaksonomiLevel(s.taksonomi_kode)));
      if (maxChild > 0 && maxChild < parent) {
        taksonomiWarnings.push({
          kode: k.kode,
          pesan: `Level Sub-CPMK tertinggi (${maxChild}) di bawah target CPMK (${parent}). Mahasiswa tak punya tahap latihan hingga level ${(k.taksonomi_kode ?? []).join(", ")}.`,
        });
      }
    }
  });

  const checks: Check[] = [
    {
      label: "Integritas CPL (semua diwadahi)",
      ok: cplList.length === 0 ? null : cplTerbengkalai.length === 0,
      detail:
        cplList.length === 0
          ? "CPL kurikulum kosong"
          : cplTerbengkalai.length === 0
            ? "Semua CPL didukung CPMK"
            : `${cplTerbengkalai.length} CPL terbengkalai`,
    },
    {
      label: "Pemetaan CPMK → CPL",
      ok: cpmk.length === 0 ? null : cpmkYatim.length === 0,
      detail:
        cpmk.length === 0
          ? "CPMK kosong"
          : cpmkYatim.length === 0
            ? "Tidak ada CPMK yatim"
            : `${cpmkYatim.length} CPMK yatim (tanpa CPL)`,
    },
    {
      label: "Dukungan Sub-CPMK → CPMK",
      ok: cpmk.length === 0 || sub.length === 0 ? null : cpmkTanpaSub.length === 0,
      detail:
        sub.length === 0
          ? "Sub-CPMK kosong"
          : cpmkTanpaSub.length === 0
            ? "Semua CPMK punya Sub-CPMK"
            : `${cpmkTanpaSub.length} CPMK tanpa Sub-CPMK`,
    },
    {
      label: "Total bobot penilaian",
      ok: komponen.length === 0 ? null : totalBobot === 100,
      detail:
        komponen.length === 0
          ? "Komponen penilaian kosong"
          : totalBobot === 100
            ? "Tepat 100%"
            : `${totalBobot}% (harus 100%)`,
    },
  ];

  const totalIsu = taksonomiWarnings.length + cplTerbengkalai.length + cpmkYatim.length;

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <div className="rounded-xl border border-border bg-surface p-4">
        <h4 className="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500">
          Pemeriksaan Kelayakan Teknis
        </h4>
        <ul className="space-y-2.5 text-xs">
          {checks.map((c) => (
            <li key={c.label} className="flex items-center justify-between gap-3">
              <span className="text-gray-600">{c.label}</span>
              <span
                className={`shrink-0 rounded px-2 py-0.5 font-semibold ${
                  c.ok === null
                    ? "text-gray-400"
                    : c.ok
                      ? "bg-emerald-50 text-emerald-700"
                      : "bg-amber-50 text-amber-700"
                }`}
              >
                {c.ok === true ? "Lulus" : c.ok === false ? c.detail : c.detail}
              </span>
            </li>
          ))}
        </ul>
      </div>

      <div className="rounded-xl border border-amber-200/60 bg-amber-50/40 p-4">
        <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-amber-800">
          Rekomendasi Taksonomi & Pemetaan ({totalIsu})
        </h4>
        <div className="max-h-48 space-y-2 overflow-y-auto text-[11px] leading-relaxed text-amber-900">
          {taksonomiWarnings.map((w, i) => (
            <div key={`t${i}`} className="border-l-2 border-amber-400 pl-2">
              <span className="font-bold">{w.kode}</span>: {w.pesan}
            </div>
          ))}
          {cplTerbengkalai.map((c) => (
            <div key={`cpl${c.id}`} className="border-l-2 border-amber-400 pl-2">
              CPL <span className="font-bold">{c.kode}</span> belum dipetakan ke CPMK manapun.
            </div>
          ))}
          {cpmkYatim.map((k) => (
            <div key={`y${k.kode}`} className="border-l-2 border-rose-400 pl-2">
              <span className="font-bold text-rose-700">{k.kode}</span> tidak mendukung CPL manapun.
            </div>
          ))}
          {totalIsu === 0 && cpmk.length > 0 && (
            <p className="italic text-gray-500">
              Tidak terdeteksi masalah keselarasan di tingkat CPL/CPMK/Sub-CPMK.
            </p>
          )}
          {cpmk.length === 0 && (
            <p className="italic text-gray-500">Jalankan generator CPMK untuk memulai diagnosis.</p>
          )}
        </div>
      </div>
    </div>
  );
}
