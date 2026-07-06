"use client";

import { useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, TextAreaField, SelectField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { ApiResult, ValidasiOverlap } from "@/lib/api";
import { pindaiOverlap, analisisOverlap, reviewOverlap } from "./actions";

type State = ApiResult | null;

const STATUS_OPTS = [
  { value: "perlu_review", label: "Perlu Ditinjau" },
  { value: "overlap", label: "Overlap (perlu dipisahkan)" },
  { value: "aman", label: "Aman (pengulangan disengaja)" },
];

/** Tombol pemindaian: mendeteksi ulang seluruh overlap. */
export function PindaiButton() {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [pesan, setPesan] = useState<string | null>(null);

  return (
    <div className="flex items-center gap-3">
      {pesan && <span className="text-xs text-muted">{pesan}</span>}
      <button
        type="button"
        disabled={pending}
        className={buttonClass("primary")}
        onClick={async () => {
          setPending(true);
          setPesan(null);
          const r = await pindaiOverlap();
          setPending(false);
          if (r.ok) {
            const d = (r.data as { data?: { overlap?: number; baru?: number } } | undefined)?.data;
            setPesan(`Ditemukan ${d?.overlap ?? 0} overlap (${d?.baru ?? 0} baru).`);
            router.refresh();
          } else {
            setPesan(r.message ?? "Pemindaian gagal.");
          }
        }}
      >
        {pending ? "Memindai…" : "↻ Pindai Overlap"}
      </button>
    </div>
  );
}

/** Tombol analisis AI per baris. */
export function AnalisisButton({ id }: { id: number }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  return (
    <button
      type="button"
      disabled={pending}
      className={buttonClass("secondary", "sm")}
      onClick={async () => {
        setPending(true);
        await analisisOverlap(id);
        setPending(false);
        router.refresh();
      }}
    >
      {pending ? "Menganalisis…" : "✨ Analisis AI"}
    </button>
  );
}

/** Modal tinjauan manusia untuk menetapkan status akhir + rekomendasi. */
export function ReviewButton({ overlap }: { overlap: ValidasiOverlap }) {
  return (
    <Modal trigger="Tinjau" title="Tinjauan Overlap" triggerVariant="ghost" triggerSize="sm">
      {(close) => <ReviewForm overlap={overlap} close={close} />}
    </Modal>
  );
}

function ReviewForm({ overlap, close }: { overlap: ValidasiOverlap; close: () => void }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => {
    setPending(true);
    const r = await reviewOverlap({
      id: overlap.id,
      status: String(fd.get("status") ?? "perlu_review"),
      rekomendasi: String(fd.get("rekomendasi") ?? ""),
    });
    setPending(false);
    return r;
  }, null);

  useEffect(() => {
    if (state?.ok) {
      router.refresh();
      close();
    }
  }, [state, close, router]);

  return (
    <form action={action} className="space-y-3">
      <div className="rounded-lg border border-border bg-gray-50 px-3 py-2 text-xs text-muted">
        <p className="font-medium text-ink">{overlap.keterampilan?.deskripsi ?? "Keterampilan"}</p>
        <p className="mt-0.5">
          Diklaim oleh: {overlap.mk_terlibat.map((m) => m.kode_mk).join(", ")}
        </p>
      </div>
      <SelectField label="Status" name="status" options={STATUS_OPTS} defaultValue={overlap.status} required />
      <TextAreaField
        label="Rekomendasi / Catatan"
        name="rekomendasi"
        defaultValue={overlap.rekomendasi ?? ""}
        rows={4}
        placeholder="Mis. Pisahkan fokus: MK dasar untuk pengenalan, MK lanjut untuk penerapan kasus."
      />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Menyimpan…" : "Simpan Tinjauan"}
        </button>
      </div>
    </form>
  );
}
