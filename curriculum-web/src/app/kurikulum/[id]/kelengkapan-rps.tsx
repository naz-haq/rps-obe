"use client";

import { Modal } from "@/components/modal";
import type { BukuKelengkapan } from "@/lib/api";

/** Modal daftar mata kuliah yang belum memiliki RPS, dikelompokkan per semester. */
export function KelengkapanRpsModal({ kelengkapan }: { kelengkapan: BukuKelengkapan }) {
  const belum = kelengkapan.mk_belum_rps;

  // Kelompokkan per semester (null → "Lainnya"), urut menaik.
  const grup = new Map<number | null, typeof belum>();
  for (const m of belum) {
    const key = m.semester ?? null;
    const list = grup.get(key) ?? [];
    list.push(m);
    grup.set(key, list);
  }
  const semesters = [...grup.keys()].sort((a, b) => (a ?? 999) - (b ?? 999));

  return (
    <Modal
      trigger={`Lihat ${belum.length} MK belum ber-RPS`}
      title="Mata Kuliah yang belum memiliki RPS"
      triggerVariant="secondary"
      triggerSize="sm"
      size="lg"
    >
      {() => (
        <div className="space-y-4">
          <p className="text-sm text-muted">
            {kelengkapan.mk_ada_rps} dari {kelengkapan.total_mk} mata kuliah sudah memiliki RPS. Buku Kurikulum dapat
            dibuat setelah seluruh mata kuliah memiliki RPS.
          </p>
          {semesters.map((sem) => {
            const list = grup.get(sem) ?? [];
            return (
              <div key={sem ?? "lainnya"}>
                <h4 className="mb-1.5 text-xs font-semibold text-brand-700">
                  {sem !== null ? `Semester ${sem}` : "Tanpa Semester"} · {list.length} MK
                </h4>
                <ul className="flex flex-wrap gap-1.5">
                  {list.map((m) => (
                    <li
                      key={m.kode_mk}
                      className="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800"
                    >
                      <span className="font-medium">{m.kode_mk}</span> — {m.nama}
                    </li>
                  ))}
                </ul>
              </div>
            );
          })}
        </div>
      )}
    </Modal>
  );
}
