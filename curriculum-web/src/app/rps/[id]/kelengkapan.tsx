"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { RpsObe, RpsVersion } from "@/lib/api";
import { simpanKelengkapan } from "./actions";

const inputCls =
  "w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400";

const OTORISASI: { key: "koordinator_mk" | "koordinator_bk" | "ketua_prodi"; idx: number }[] = [
  { key: "koordinator_mk", idx: 0 },
  { key: "koordinator_bk", idx: 1 },
  { key: "ketua_prodi", idx: 2 },
];

function Bagian({ judul, children }: { judul: string; children: React.ReactNode }) {
  return (
    <fieldset className="space-y-3 rounded-xl border border-border p-4">
      <legend className="px-1 text-xs font-semibold uppercase tracking-wide text-muted">{judul}</legend>
      {children}
    </fieldset>
  );
}

/** Tombol + modal pengisian kelengkapan dokumen RPS format OBE. Kosong = pakai nilai turunan sistem. */
export function KelengkapanButton({ rps, obe }: { rps: RpsVersion; obe: RpsObe }) {
  return (
    <Modal trigger="Lengkapi Dokumen" triggerVariant="secondary" triggerSize="sm" title="Kelengkapan Dokumen RPS" size="lg">
      {(close) => <KelengkapanForm rps={rps} obe={obe} close={close} />}
    </Modal>
  );
}

function KelengkapanForm({ rps, obe, close }: { rps: RpsVersion; obe: RpsObe; close: () => void }) {
  const kel = rps.kelengkapan ?? {};
  const router = useRouter();
  const toast = useToast();
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);

  const submit = (fd: FormData) => {
    setError(null);
    start(async () => {
      const res = await simpanKelengkapan(rps.id, fd);
      if (res.ok) {
        toast({ type: "success", message: "Kelengkapan dokumen tersimpan." });
        close();
        router.refresh();
      } else {
        setError(res.message ?? "Gagal menyimpan kelengkapan dokumen.");
      }
    });
  };

  return (
    <form action={submit} className="space-y-4 p-5">
      <p className="text-xs text-muted">Kolom yang dikosongkan memakai nilai otomatis dari data RPS (ditampilkan sebagai teks abu-abu).</p>

      <Bagian judul="Identitas Dokumen">
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Kode/No. Dokumen" name="kode_dokumen" defaultValue={rps.kode_dokumen ?? ""} placeholder="mis. FM-RPS-FF-01" />
          <Field label="Tanggal Penyusunan" name="tanggal_penyusunan" type="date" defaultValue={rps.tanggal_penyusunan ?? ""} />
          <Field label="Tahun Akademik" name="tahun_akademik" defaultValue={rps.tahun_akademik ?? ""} placeholder="mis. 2026/2027" hint="Format YYYY/YYYY." />
          <Field
            label="Berlaku Mulai"
            name="berlaku_mulai"
            type="date"
            defaultValue={rps.berlaku_mulai ?? ""}
            hint={obe.kop.berlaku_mulai && !rps.berlaku_mulai ? `Otomatis: ${obe.kop.berlaku_mulai} (tanggal disetujui)` : undefined}
          />
        </div>
      </Bagian>

      <Bagian judul="Otorisasi">
        {OTORISASI.map(({ key, idx }) => {
          const turunan = obe.otorisasi[idx];
          const manual = kel.otorisasi?.[key];
          return (
            <div key={key} className="grid gap-3 sm:grid-cols-[1.2fr_1fr]">
              <Field label={`${turunan.jabatan} — Nama`} name={`${key}_nama`} defaultValue={manual?.nama ?? ""} placeholder={turunan.nama ?? "Nama lengkap dan gelar"} />
              <Field label="NIDN/NIP" name={`${key}_nidn`} defaultValue={manual?.nidn ?? ""} placeholder={turunan.nidn ?? "—"} />
            </div>
          );
        })}
      </Bagian>

      {obe.cpl.length > 0 && (
        <Bagian judul="Kontribusi MK terhadap CPL">
          <div className="grid gap-2 sm:grid-cols-2">
            {obe.cpl.map((c) => (
              <label key={c.kode} className="flex items-center justify-between gap-3 text-sm">
                <span className="font-medium text-ink" title={c.deskripsi}>{c.kode}</span>
                <select name={`peran_cpl__${c.kode}`} defaultValue={kel.peran_cpl?.[c.kode] ?? ""} className={`${inputCls} max-w-[12rem]`}>
                  <option value="">Otomatis ({c.kontribusi})</option>
                  <option value="utama">Utama</option>
                  <option value="pendukung">Pendukung</option>
                </select>
              </label>
            ))}
          </div>
        </Bagian>
      )}

      <Bagian judul="Media, LMS, dan Sumber Pembelajaran">
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Media Pembelajaran</span>
          <textarea name="media_pembelajaran" rows={2} defaultValue={kel.media_pembelajaran ?? ""} placeholder={obe.media.media_pembelajaran ?? "PPT, video, simulasi, alat laboratorium, software, dsb."} className={inputCls} />
        </label>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="LMS" name="lms" defaultValue={kel.lms ?? ""} placeholder="mis. Kalam" />
          <Field
            label="Proporsi Luring (%)"
            name="proporsi_luring"
            type="number"
            defaultValue={kel.proporsi_luring ?? ""}
            placeholder="mis. 80"
            hint="Proporsi daring dihitung otomatis (100 − luring)."
          />
        </div>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Pengalaman Belajar Utama</span>
          <textarea name="pengalaman_belajar_utama" rows={2} defaultValue={kel.pengalaman_belajar_utama ?? ""} placeholder={obe.media.pengalaman_belajar_utama ?? "Diskusi, case method, praktikum, proyek, refleksi, dsb."} className={inputCls} />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Sumber Belajar Utama</span>
          <textarea name="sumber_belajar_utama" rows={2} defaultValue={kel.sumber_belajar_utama ?? ""} placeholder={obe.media.sumber_belajar_utama ?? "Buku, jurnal, farmakope, guideline, standar, dsb."} className={inputCls} />
        </label>
      </Bagian>

      <Bagian judul="Lampiran Instrumen">
        <div className="grid gap-2 sm:grid-cols-2">
          {obe.lampiran.map((l) => (
            <label key={l.key} className="flex items-center justify-between gap-3 text-sm">
              <span className="text-ink">{l.label}</span>
              <select
                name={`lampiran__${l.key}`}
                defaultValue={l.manual ? (l.ada ? "ada" : "tidak") : ""}
                className={`${inputCls} max-w-[11rem]`}
              >
                <option value="">{l.manual ? "Otomatis" : `Otomatis (${l.ada ? "ada" : "tidak"})`}</option>
                <option value="ada">Ada</option>
                <option value="tidak">Tidak ada</option>
              </select>
            </label>
          ))}
        </div>
      </Bagian>

      {error && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <div className="flex justify-end gap-2 border-t border-border pt-4">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Menyimpan…" : "Simpan Kelengkapan"}
        </button>
      </div>
    </form>
  );
}
