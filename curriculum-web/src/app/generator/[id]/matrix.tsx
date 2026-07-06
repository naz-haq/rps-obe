"use client";

import type { Cpl } from "@/lib/api";
import { Badge } from "@/components/ui";
import { type Draf, getCpmk, getSubCpmk } from "./draft";

export function CplCpmkMatrix({ draf, cplList }: { draf: Draf; cplList: Cpl[] }) {
  const cpmk = getCpmk(draf);
  const sub = getSubCpmk(draf);

  // Kumpulan CPL: gabungkan CPL kurikulum + kode yang dirujuk draf (bila belum ada).
  const knownKode = new Set(cplList.map((c) => c.kode));
  const extraKode = Array.from(
    new Set(cpmk.flatMap((k) => k.cpl_kode ?? []).filter((k) => !knownKode.has(k))),
  );
  const rows: { kode: string; deskripsi: string }[] = [
    ...cplList.map((c) => ({ kode: c.kode, deskripsi: c.deskripsi })),
    ...extraKode.map((k) => ({ kode: k, deskripsi: "(tidak ada di kurikulum)" })),
  ];

  return (
    <div className="space-y-6">
      <section className="rounded-xl border border-border bg-surface p-5">
        <h3 className="text-sm font-semibold text-ink">Matriks Pemetaan CPL × CPMK</h3>
        <p className="mt-1 text-xs text-muted">
          Baris = CPL prodi, kolom = CPMK mata kuliah. Tanda ✓ menandakan CPMK mendukung CPL.
        </p>
        {rows.length === 0 || cpmk.length === 0 ? (
          <div className="mt-4 rounded-lg border border-dashed border-border py-6 text-center text-xs text-muted">
            Lengkapi CPL kurikulum & CPMK untuk memunculkan matriks.
          </div>
        ) : (
          <div className="mt-4 overflow-x-auto">
            <table className="w-full border-collapse text-left text-xs">
              <thead>
                <tr className="bg-gray-50">
                  <th className="border border-border p-2 font-semibold text-gray-600">CPL</th>
                  <th className="border border-border p-2 font-semibold text-gray-600">Deskripsi</th>
                  {cpmk.map((k) => (
                    <th
                      key={k.kode}
                      className="border border-border p-2 text-center font-semibold text-gray-600"
                      title={k.deskripsi}
                    >
                      {k.kode}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.kode} className="hover:bg-gray-50">
                    <td className="border border-border p-2 font-mono font-semibold text-gray-700">
                      {r.kode}
                    </td>
                    <td className="max-w-xs truncate border border-border p-2 text-gray-600" title={r.deskripsi}>
                      {r.deskripsi}
                    </td>
                    {cpmk.map((k) => {
                      const mapped = (k.cpl_kode ?? []).includes(r.kode);
                      return (
                        <td
                          key={k.kode}
                          className={`border border-border p-2 text-center ${mapped ? "bg-emerald-50/50" : ""}`}
                        >
                          {mapped ? (
                            <span className="inline-grid h-5 w-5 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                              ✓
                            </span>
                          ) : (
                            <span className="text-gray-300">–</span>
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <section className="rounded-xl border border-border bg-surface p-5">
        <h3 className="text-sm font-semibold text-ink">Hierarki CPMK → Sub-CPMK</h3>
        <p className="mt-1 text-xs text-muted">Dekomposisi CPMK menjadi Sub-CPMK operasional.</p>
        {cpmk.length === 0 ? (
          <div className="mt-4 rounded-lg border border-dashed border-border py-6 text-center text-xs text-muted">
            Belum ada CPMK.
          </div>
        ) : (
          <div className="mt-4 space-y-3">
            {cpmk.map((k) => {
              const subs = sub.filter((s) => s.cpmk_kode === k.kode);
              return (
                <div key={k.kode} className="rounded-xl border border-border bg-gray-50/50 p-3">
                  <div className="flex flex-wrap items-center gap-2 border-b border-border pb-2">
                    <Badge tone="brand">{k.kode}</Badge>
                    {(k.taksonomi_kode ?? []).map((t) => (
                      <Badge key={t} tone="warn">{t}</Badge>
                    ))}
                    <span className="text-xs font-medium text-ink">{k.deskripsi}</span>
                  </div>
                  {subs.length === 0 ? (
                    <p className="pt-2 text-xs italic text-amber-600">
                      Belum ada Sub-CPMK yang mendukung CPMK ini.
                    </p>
                  ) : (
                    <div className="grid gap-2 pt-2 sm:grid-cols-2 lg:grid-cols-3">
                      {subs.map((s) => (
                        <div key={s.kode} className="rounded-lg border border-border bg-surface p-2.5">
                          <div className="flex flex-wrap items-center gap-1.5">
                            <Badge tone="neutral">{s.kode}</Badge>
                            {(s.taksonomi_kode ?? []).map((t) => (
                              <span key={t} className="text-[10px] font-semibold text-amber-600">
                                {t}
                              </span>
                            ))}
                          </div>
                          <p className="mt-1 text-[11px] leading-normal text-gray-600">{s.deskripsi}</p>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </section>
    </div>
  );
}
