"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { buatEvaluasi, hapusEvaluasi } from "./actions";

type CplOpt = { value: string; label: string };

export function BuatEvaluasi({ cplOptions }: { cplOptions: CplOpt[] }) {
  return (
    <Modal trigger="+ Evaluasi CPL" title="Buat Evaluasi CPL" triggerVariant="primary">
      {(close) => <EvaluasiForm cplOptions={cplOptions} close={close} />}
    </Modal>
  );
}

function EvaluasiForm({ cplOptions, close }: { cplOptions: CplOpt[]; close: () => void }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <form
      action={async (fd) => {
        setPending(true);
        setError(null);
        const r = await buatEvaluasi({
          cpl_id: Number(fd.get("cpl_id")),
          periode: String(fd.get("periode") ?? ""),
        });
        setPending(false);
        if (r.ok) {
          const id = (r.data as { data?: { id?: number } } | undefined)?.data?.id;
          if (id) router.push(`/obaei/evaluasi/${id}`);
          else router.refresh();
          close();
        } else {
          setError(r.message ?? "Gagal membuat evaluasi.");
        }
      }}
      className="space-y-3"
    >
      <SelectField label="CPL" name="cpl_id" options={cplOptions} defaultValue={cplOptions[0]?.value} required />
      <Field label="Periode" name="periode" placeholder="mis. 2024/2025 Ganjil" />
      {error && <p className="text-xs text-red-600">{error}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Membuat…" : "Buat & Buka"}
        </button>
      </div>
    </form>
  );
}

export function HapusEvaluasi({ id }: { id: number }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  return (
    <button
      type="button"
      disabled={pending}
      className={buttonClass("danger", "sm")}
      onClick={async () => {
        if (!confirm("Hapus evaluasi ini beserta tindak lanjutnya?")) return;
        setPending(true);
        await hapusEvaluasi(id);
        setPending(false);
        router.refresh();
      }}
    >
      Hapus
    </button>
  );
}
