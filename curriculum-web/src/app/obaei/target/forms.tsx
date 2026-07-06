"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { TargetCpl } from "@/lib/api";
import { simpanTarget, hapusTarget } from "./actions";

type CplOpt = { value: string; label: string };

export function TambahTarget({ cplOptions }: { cplOptions: CplOpt[] }) {
  return (
    <Modal trigger="+ Target CPL" title="Set Target CPL" triggerVariant="primary">
      {(close) => <TargetForm cplOptions={cplOptions} close={close} />}
    </Modal>
  );
}

export function EditTarget({ target, cplOptions }: { target: TargetCpl; cplOptions: CplOpt[] }) {
  return (
    <Modal trigger="Ubah" title="Ubah Target CPL" triggerVariant="ghost" triggerSize="sm">
      {(close) => <TargetForm target={target} cplOptions={cplOptions} close={close} />}
    </Modal>
  );
}

function TargetForm({
  target,
  cplOptions,
  close,
}: {
  target?: TargetCpl;
  cplOptions: CplOpt[];
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
        const r = await simpanTarget({
          id: target?.id,
          cpl_id: Number(fd.get("cpl_id")),
          angkatan: String(fd.get("angkatan") ?? ""),
          ambang_nilai: fd.get("ambang_nilai") ? Number(fd.get("ambang_nilai")) : null,
          persentase_target: fd.get("persentase_target") ? Number(fd.get("persentase_target")) : null,
        });
        setPending(false);
        if (r.ok) {
          router.refresh();
          close();
        } else {
          setError(r.message ?? "Gagal menyimpan target.");
        }
      }}
      className="space-y-3"
    >
      <SelectField
        label="CPL"
        name="cpl_id"
        options={cplOptions}
        defaultValue={target ? String(target.cpl_id) : cplOptions[0]?.value}
        required
      />
      <Field label="Angkatan" name="angkatan" defaultValue={target?.angkatan ?? ""} placeholder="mis. 2024" />
      <div className="grid grid-cols-2 gap-3">
        <Field
          label="Ambang Nilai (0-100)"
          name="ambang_nilai"
          type="number"
          defaultValue={target?.ambang_nilai ?? ""}
          placeholder="mis. 60"
          hint="Nilai minimal dianggap lulus CPL"
        />
        <Field
          label="% Target Mahasiswa"
          name="persentase_target"
          type="number"
          defaultValue={target?.persentase_target ?? ""}
          placeholder="mis. 75"
          hint="% mahasiswa yang harus mencapai ambang"
        />
      </div>
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

export function HapusTarget({ id }: { id: number }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  return (
    <button
      type="button"
      disabled={pending}
      className={buttonClass("danger", "sm")}
      onClick={async () => {
        if (!confirm("Hapus target CPL ini?")) return;
        setPending(true);
        await hapusTarget(id);
        setPending(false);
        router.refresh();
      }}
    >
      Hapus
    </button>
  );
}
