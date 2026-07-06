"use client";

import { useActionState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Modal, SelectField, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { MataKuliah, ApiResult } from "@/lib/api";
import { startSession } from "./actions";

type State = ApiResult | null;

export function StartSessionButton({ mataKuliah }: { mataKuliah: MataKuliah[] }) {
  return (
    <Modal trigger="+ Sesi Baru" title="Mulai Sesi Penyusunan RPS">
      {(close) => <StartForm close={close} mataKuliah={mataKuliah} />}
    </Modal>
  );
}

function StartForm({ close, mataKuliah }: { close: () => void; mataKuliah: MataKuliah[] }) {
  const router = useRouter();
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => startSession(fd), null);
  useEffect(() => {
    if (state?.ok) {
      const id = (state.data as { id?: number } | undefined)?.id;
      close();
      if (id) router.push(`/generator/${id}`);
      else router.refresh();
    }
  }, [state, close, router]);

  if (mataKuliah.length === 0) {
    return (
      <div className="space-y-3">
        <p className="text-sm text-muted">
          Belum ada mata kuliah. Impor mata kuliah lewat Onboarding terlebih dahulu.
        </p>
        <div className="flex justify-end">
          <button type="button" onClick={close} className={buttonClass("secondary")}>
            Tutup
          </button>
        </div>
      </div>
    );
  }

  return (
    <form action={action} className="space-y-4">
      <SelectField
        label="Mata Kuliah"
        name="mk_id"
        required
        options={mataKuliah.map((m) => ({ value: String(m.id), label: `${m.kode_mk} — ${m.nama}` }))}
      />
      <SelectField
        label="Sumber"
        name="sumber"
        defaultValue="baru"
        options={[
          { value: "baru", label: "Susun baru" },
          { value: "impor_rps_lama", label: "Impor RPS lama" },
          { value: "copy_tahun_lalu", label: "Salin tahun lalu" },
        ]}
      />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <SubmitButton>Mulai</SubmitButton>
      </div>
    </form>
  );
}
