"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, TextAreaField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { tinjau } from "./actions";

/** Aksi cepat Setujui / Minta Revisi pada baris antrian tinjauan. */
export function TinjauActions({ id }: { id: number }) {
  return (
    <div className="flex justify-end gap-1.5">
      <TinjauModal id={id} aksi="setujui" trigger="Setujui" title="Setujui RPS" variant="primary" wajib={false} konfirmasi="Setujui & Kunci" />
      <TinjauModal id={id} aksi="revisi" trigger="Minta Revisi" title="Minta Revisi" variant="danger" wajib konfirmasi="Kirim Revisi" />
    </div>
  );
}

function TinjauModal({
  id,
  aksi,
  trigger,
  title,
  variant,
  wajib,
  konfirmasi,
}: {
  id: number;
  aksi: "setujui" | "revisi";
  trigger: string;
  title: string;
  variant: "primary" | "danger";
  wajib: boolean;
  konfirmasi: string;
}) {
  return (
    <Modal trigger={trigger} title={title} triggerVariant={variant} triggerSize="sm">
      {(close) => <TinjauForm id={id} aksi={aksi} wajib={wajib} konfirmasi={konfirmasi} close={close} />}
    </Modal>
  );
}

function TinjauForm({
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
        const r = await tinjau({
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
        placeholder={wajib ? "Jelaskan bagian yang perlu diperbaiki penyusun." : "Catatan persetujuan (opsional)."}
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
