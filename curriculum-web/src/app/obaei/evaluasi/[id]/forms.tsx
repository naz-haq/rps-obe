"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, TextAreaField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { TindakLanjut } from "@/lib/api";
import {
  analisisEvaluasi,
  finalisasiEvaluasi,
  ubahEvaluasi,
  tambahTindakLanjut,
  ubahTindakLanjut,
  hapusTindakLanjut,
} from "../actions";

const PRIORITAS_OPTS = [
  { value: "", label: "— Tanpa prioritas —" },
  { value: "tinggi", label: "Tinggi" },
  { value: "sedang", label: "Sedang" },
  { value: "rendah", label: "Rendah" },
];

export function AnalisisAiButton({ id, angkatan }: { id: number; angkatan?: string }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  return (
    <div className="flex items-center gap-2">
      {error && <span className="text-xs text-red-600">{error}</span>}
      {info && !error && <span className="text-xs text-muted">{info}</span>}
      <button
        type="button"
        disabled={pending}
        className={buttonClass("primary")}
        onClick={async () => {
          setPending(true);
          setError(null);
          setInfo(null);
          const r = await analisisEvaluasi(id, angkatan);
          setPending(false);
          if (r.ok) {
            const ev = (r.data as { data?: { ringkasan_naratif?: string | null; tindak_lanjut?: unknown[] } } | undefined)?.data;
            const adaRingkasan = Boolean(ev?.ringkasan_naratif);
            const jmlTindak = ev?.tindak_lanjut?.length ?? 0;
            if (!adaRingkasan && jmlTindak === 0) {
              setInfo("AI belum menghasilkan analisis. Pastikan Target CPL & Capaian Mahasiswa sudah terisi.");
            }
            toast({ type: "success", message: "Analisis AI selesai." });
            router.refresh();
          } else {
            setError(r.message ?? "Analisis gagal.");
            toast({ type: "error", message: r.message ?? "Analisis gagal." });
          }
        }}
      >
        {pending ? "Menganalisis…" : "✨ Analisis AI"}
      </button>
    </div>
  );
}

export function FinalisasiButton({ id }: { id: number }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  return (
    <button
      type="button"
      disabled={pending}
      className={buttonClass("secondary")}
      onClick={async () => {
        if (!confirm("Finalisasi evaluasi ini? Setelah final, evaluasi menjadi bukti resmi.")) return;
        setPending(true);
        const r = await finalisasiEvaluasi(id);
        setPending(false);
        if (r.ok) {
          toast({ type: "success", message: "Evaluasi difinalisasi." });
          router.refresh();
        } else {
          toast({ type: "error", message: r.message ?? "Gagal memfinalisasi evaluasi." });
        }
      }}
    >
      Finalisasi
    </button>
  );
}

export function EditRingkasan({ id, periode, ringkasan }: { id: number; periode: string | null; ringkasan: string | null }) {
  return (
    <Modal trigger="Ubah Ringkasan" title="Ubah Ringkasan Evaluasi" triggerVariant="ghost" triggerSize="sm">
      {(close) => <RingkasanForm id={id} periode={periode} ringkasan={ringkasan} close={close} />}
    </Modal>
  );
}

function RingkasanForm({ id, periode, ringkasan, close }: { id: number; periode: string | null; ringkasan: string | null; close: () => void }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  return (
    <form
      action={async (fd) => {
        setPending(true);
        const r = await ubahEvaluasi(id, {
          periode: String(fd.get("periode") ?? ""),
          ringkasan_naratif: String(fd.get("ringkasan_naratif") ?? ""),
        });
        setPending(false);
        if (r.ok) {
          toast({ type: "success", message: "Ringkasan tersimpan." });
          router.refresh();
          close();
        } else {
          toast({ type: "error", message: r.message ?? "Gagal menyimpan ringkasan." });
        }
      }}
      className="space-y-3"
    >
      <Field label="Periode" name="periode" defaultValue={periode ?? ""} placeholder="mis. 2024/2025 Ganjil" />
      <TextAreaField label="Ringkasan Naratif" name="ringkasan_naratif" defaultValue={ringkasan ?? ""} rows={8} />
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Menyimpan…" : "Simpan"}
        </button>
      </div>
    </form>
  );
}

export function TambahTindakLanjut({ evaluasiId }: { evaluasiId: number }) {
  return (
    <Modal trigger="+ Tindak Lanjut" title="Tambah Tindak Lanjut" triggerVariant="primary" triggerSize="sm">
      {(close) => <TindakLanjutForm evaluasiId={evaluasiId} close={close} />}
    </Modal>
  );
}

export function EditTindakLanjut({ evaluasiId, item }: { evaluasiId: number; item: TindakLanjut }) {
  return (
    <Modal trigger="Ubah" title="Ubah Tindak Lanjut" triggerVariant="ghost" triggerSize="sm">
      {(close) => <TindakLanjutForm evaluasiId={evaluasiId} item={item} close={close} />}
    </Modal>
  );
}

function TindakLanjutForm({ evaluasiId, item, close }: { evaluasiId: number; item?: TindakLanjut; close: () => void }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  return (
    <form
      action={async (fd) => {
        setPending(true);
        setError(null);
        const catatan = String(fd.get("catatan") ?? "").trim();
        const prioritas = String(fd.get("prioritas") ?? "");
        const r = item
          ? await ubahTindakLanjut(item.id, evaluasiId, { catatan, prioritas, status: item.status ?? "" })
          : await tambahTindakLanjut(evaluasiId, { catatan, prioritas });
        setPending(false);
        if (r.ok) {
          toast({ type: "success", message: item ? "Tindak lanjut diperbarui." : "Tindak lanjut ditambahkan." });
          router.refresh();
          close();
        } else {
          setError(r.message ?? "Gagal menyimpan tindak lanjut.");
          toast({ type: "error", message: r.message ?? "Gagal menyimpan tindak lanjut." });
        }
      }}
      className="space-y-3"
    >
      <TextAreaField label="Catatan / Rencana Perbaikan" name="catatan" defaultValue={item?.catatan ?? ""} rows={4} required />
      <SelectField label="Prioritas" name="prioritas" options={PRIORITAS_OPTS} defaultValue={item?.prioritas ?? ""} />
      {error && <p className="text-xs text-red-600">{error}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Menyimpan…" : "Simpan"}
        </button>
      </div>
    </form>
  );
}

export function HapusTindakLanjut({ id, evaluasiId }: { id: number; evaluasiId: number }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  return (
    <button
      type="button"
      disabled={pending}
      className={buttonClass("danger", "sm")}
      onClick={async () => {
        if (!confirm("Hapus tindak lanjut ini?")) return;
        setPending(true);
        const r = await hapusTindakLanjut(id, evaluasiId);
        setPending(false);
        if (r.ok) {
          toast({ type: "success", message: "Tindak lanjut dihapus." });
          router.refresh();
        } else {
          toast({ type: "error", message: r.message ?? "Gagal menghapus tindak lanjut." });
        }
      }}
    >
      Hapus
    </button>
  );
}
