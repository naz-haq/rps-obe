"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, AiTextArea, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { Cpl, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createCpl, updateCpl, deleteCpl } from "./actions";

const ASPEK_OPTS = [
  { value: "", label: "— Tanpa aspek —" },
  { value: "sikap", label: "Sikap" },
  { value: "pengetahuan", label: "Pengetahuan" },
  { value: "keterampilan_umum", label: "Keterampilan Umum" },
  { value: "keterampilan_khusus", label: "Keterampilan Khusus" },
];

type State = ApiResult | null;

function CplFields({ kurikulumId, c }: { kurikulumId: number; c?: Cpl }) {
  return (
    <div className="space-y-3">
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      {c && <input type="hidden" name="id" value={c.id} />}
      <div className="grid grid-cols-2 gap-3">
        <Field label="Kode" name="kode" defaultValue={c?.kode ?? ""} required placeholder="CPL-01" />
        <SelectField label="Aspek" name="aspek" options={ASPEK_OPTS} defaultValue={c?.aspek ?? ""} />
      </div>
      <AiTextArea label="Deskripsi" name="deskripsi" defaultValue={c?.deskripsi ?? ""} required rows={4} placeholder="Mampu ..." konteks="Deskripsi CPL" />
      <div className="grid grid-cols-2 gap-3">
        <Field label="Level KKNI" name="level_kkni" defaultValue={c?.level_kkni ?? ""} placeholder="6" />
        <Field label="Sumber" name="sumber" defaultValue={c?.sumber ?? ""} placeholder="Asosiasi / SN-Dikti" />
      </div>
    </div>
  );
}

export function CreateCplButton({ kurikulumId }: { kurikulumId: number }) {
  return (
    <Modal trigger="+ CPL" title="Tambah CPL">
      {(close) => <CreateForm kurikulumId={kurikulumId} close={close} />}
    </Modal>
  );
}

function CreateForm({ kurikulumId, close }: { kurikulumId: number; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createCpl(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "CPL berhasil disimpan." });
  return (
    <form action={action} className="space-y-4">
      <CplFields kurikulumId={kurikulumId} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

export function EditCplButton({ c, kurikulumId }: { c: Cpl; kurikulumId: number }) {
  return (
    <Modal trigger="Edit" title="Ubah CPL" triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditForm c={c} kurikulumId={kurikulumId} close={close} />}
    </Modal>
  );
}

function EditForm({ c, kurikulumId, close }: { c: Cpl; kurikulumId: number; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateCpl(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "CPL berhasil diperbarui." });
  return (
    <form action={action} className="space-y-4">
      <CplFields c={c} kurikulumId={kurikulumId} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Perbarui</SubmitButton>
      </div>
    </form>
  );
}

export function DeleteCplButton({ c, kurikulumId }: { c: Cpl; kurikulumId: number }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus CPL" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm c={c} kurikulumId={kurikulumId} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ c, kurikulumId, close, onDone }: { c: Cpl; kurikulumId: number; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteCpl(fd), null);
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "CPL dihapus." });
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={c.id} />
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      <p className="text-sm text-muted">
        Hapus CPL <span className="font-medium text-ink">{c.kode}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}
