"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { buttonClass } from "@/components/ui";
import type { MataKuliah } from "@/lib/api";
import { ReferensiEditor } from "@/app/kurikulum/[id]/mata-kuliah/referensi-editor";
import { DokumenTautanEditor } from "@/app/kurikulum/[id]/mata-kuliah/dokumen-tautan-editor";
import { saveDetailMk } from "../actions";

/**
 * Detail MK yang menjadi tanggung jawab DOSEN pengampu (deskripsi, pustaka,
 * sumber materi) — diisi sekali di sini, tersimpan ke master MK, sehingga
 * modul Kurikulum cukup mengurus struktur (kode/SKS/CPL/bahan kajian).
 */
export function DetailMkPanel({ sessionId, mk, committed }: { sessionId: number; mk: MataKuliah; committed: boolean }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [pesan, setPesan] = useState<{ tone: "ok" | "err"; text: string } | null>(null);
  const [pending, startTransition] = useTransition();

  const lengkap = [
    (mk.deskripsi_singkat ?? "").trim() !== "" ? "Deskripsi" : null,
  ].filter(Boolean) as string[];

  const submit = (fd: FormData) => {
    setPesan(null);
    startTransition(async () => {
      const res = await saveDetailMk(sessionId, fd);
      if (res.ok) {
        setPesan({ tone: "ok", text: "Detail MK tersimpan — dipakai pada generate berikutnya." });
        setOpen(false);
        router.refresh();
      } else {
        setPesan({ tone: "err", text: res.message ?? "Gagal menyimpan detail MK." });
      }
    });
  };

  return (
    <section className="rounded-xl border border-border bg-surface p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold text-ink">Detail MK oleh Dosen</h3>
          <p className="text-xs text-muted">
            Deskripsi, pustaka/referensi, dan sumber materi (buku/artikel) — tersimpan ke master MK, cukup diisi sekali.
            {lengkap.length === 0 && " Deskripsi belum terisi."}
          </p>
        </div>
        {!committed && (
          <button type="button" onClick={() => setOpen((v) => !v)} className={buttonClass("secondary", "sm")}>
            {open ? "Tutup" : "Lengkapi / Ubah"}
          </button>
        )}
      </div>

      {pesan && (
        <p className={`mt-2 text-xs ${pesan.tone === "ok" ? "text-emerald-700" : "text-red-600"}`}>{pesan.text}</p>
      )}

      {open && (
        <div className="mt-3 space-y-4">
          <form action={submit} className="space-y-3">
            <input type="hidden" name="mk_id" value={mk.id} />
            <input type="hidden" name="institusi_id" value={mk.institusi_id} />
            <input type="hidden" name="kode_mk" value={mk.kode_mk} />
            {/* konteks utk tombol "Saran AI" pustaka */}
            <input type="hidden" name="nama" value={mk.nama ?? ""} />
            <input type="hidden" name="jenis_mk" value={mk.jenis_mk ?? ""} />
            <input type="hidden" name="sks_teori" value={mk.sks_teori ?? ""} />
            <input type="hidden" name="sks_praktik" value={mk.sks_praktik ?? ""} />

            <label className="block">
              <span className="mb-1 block text-xs font-medium text-ink">Deskripsi Singkat MK</span>
              <textarea
                name="deskripsi_singkat"
                rows={4}
                defaultValue={mk.deskripsi_singkat ?? ""}
                placeholder="Ringkasan mata kuliah — dipakai AI sebagai konteks utama…"
                className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
              />
            </label>

            <ReferensiEditor mk={mk} />

            <div className="flex justify-end">
              <button type="submit" disabled={pending} className={buttonClass("primary", "sm")}>
                {pending ? "Menyimpan…" : "Simpan Detail MK"}
              </button>
            </div>
          </form>

          {/* Dokumen tertaut tersimpan langsung via API sendiri (tanpa tombol simpan). */}
          <DokumenTautanEditor mk={mk} />
        </div>
      )}
    </section>
  );
}
