"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, TextAreaField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { aksiTersedia } from "@/lib/rps-status";
import { aksiPersetujuan } from "./actions";

/** Bilah aksi persetujuan pada halaman detail RPS. Tampilan menyesuaikan status. */
export function ApprovalActions({ id, status }: { id: number; status: string }) {
  const aksi = aksiTersedia(status);
  const router = useRouter();
  const [pending, setPending] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function jalankan(a: "ajukan" | "tarik") {
    setPending(a);
    setError(null);
    const r = await aksiPersetujuan({ id, aksi: a });
    setPending(null);
    if (r.ok) router.refresh();
    else setError(r.message ?? "Gagal memproses aksi.");
  }

  if (status === "approved") {
    return <span className="text-sm font-medium text-emerald-700">✓ Disetujui &amp; terkunci</span>;
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <div className="flex items-center gap-2">
        {aksi.ajukan && (
          <button
            type="button"
            disabled={pending !== null}
            className={buttonClass("primary")}
            onClick={() => jalankan("ajukan")}
          >
            {pending === "ajukan" ? "Mengajukan…" : "Ajukan untuk Tinjauan"}
          </button>
        )}
        {aksi.setujui && (
          <CatatanModal
            id={id}
            aksi="setujui"
            trigger="Setujui"
            title="Setujui RPS"
            triggerVariant="primary"
            wajib={false}
            konfirmasi="Setujui & Kunci"
          />
        )}
        {aksi.revisi && (
          <CatatanModal
            id={id}
            aksi="revisi"
            trigger="Minta Revisi"
            title="Minta Revisi RPS"
            triggerVariant="danger"
            wajib
            konfirmasi="Kirim Revisi"
          />
        )}
        {aksi.tarik && (
          <button
            type="button"
            disabled={pending !== null}
            className={buttonClass("secondary")}
            onClick={() => jalankan("tarik")}
          >
            {pending === "tarik" ? "Menarik…" : "Tarik"}
          </button>
        )}
      </div>
      {error && <span className="text-xs text-red-600">{error}</span>}
    </div>
  );
}

function CatatanModal({
  id,
  aksi,
  trigger,
  title,
  triggerVariant,
  wajib,
  konfirmasi,
}: {
  id: number;
  aksi: "setujui" | "revisi";
  trigger: string;
  title: string;
  triggerVariant: "primary" | "danger";
  wajib: boolean;
  konfirmasi: string;
}) {
  return (
    <Modal trigger={trigger} title={title} triggerVariant={triggerVariant}>
      {(close) => <CatatanForm id={id} aksi={aksi} wajib={wajib} konfirmasi={konfirmasi} close={close} />}
    </Modal>
  );
}

function CatatanForm({
  id,
  aksi,
  wajib,
  konfirmasi,
  close,
}: {
  id: number;
  aksi: "setujui" | "revisi";
  wajib: boolean;
  konfirmasi: string;
  close: () => void;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <form
      action={async (fd) => {
        setPending(true);
        setError(null);
        const r = await aksiPersetujuan({
          id,
          aksi,
          catatan: String(fd.get("catatan") ?? ""),
          actor_nama: String(fd.get("actor_nama") ?? "") || undefined,
        });
        setPending(false);
        if (r.ok) {
          router.refresh();
          close();
        } else {
          setError(r.message ?? "Gagal memproses aksi.");
        }
      }}
      className="space-y-3"
    >
      <TextAreaField
        label={wajib ? "Catatan Revisi" : "Catatan (opsional)"}
        name="catatan"
        required={wajib}
        rows={4}
        placeholder={
          wajib
            ? "Jelaskan bagian yang perlu diperbaiki penyusun."
            : "Catatan persetujuan, mis. sudah sesuai pedoman KPT."
        }
      />
      <label className="block">
        <span className="mb-1 block text-xs font-medium text-ink">Nama Peninjau (opsional)</span>
        <input
          name="actor_nama"
          className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
          placeholder="mis. Kaprodi / STPMP"
        />
      </label>
      {error && <p className="text-xs text-red-600">{error}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass(aksi === "revisi" ? "danger" : "primary")}>
          {pending ? "Memproses…" : konfirmasi}
        </button>
      </div>
    </form>
  );
}
