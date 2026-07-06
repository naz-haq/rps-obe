"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { Kurikulum, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createKurikulum, updateKurikulum, deleteKurikulum } from "./actions";

const STATUS_OPTS = [
  { value: "draft", label: "Draft" },
  { value: "berlaku", label: "Berlaku" },
  { value: "arsip", label: "Arsip" },
];

type State = ApiResult | null;

function KurikulumFields({ k }: { k?: Kurikulum }) {
  return (
    <div className="space-y-3">
      {k && <input type="hidden" name="id" value={k.id} />}
      <Field label="Nama Kurikulum" name="nama" defaultValue={k?.nama ?? ""} required placeholder="Kurikulum OBE 2024" />
      <div className="grid grid-cols-2 gap-3">
        <Field label="Kode" name="kode" defaultValue={k?.kode ?? ""} placeholder="KUR-2024" />
        <Field label="Tahun" name="tahun" defaultValue={k?.tahun ?? ""} required placeholder="2024" />
      </div>
      <SelectField label="Status" name="status" options={STATUS_OPTS} defaultValue={k?.status ?? "draft"} />
    </div>
  );
}

export function CreateKurikulumButton() {
  return (
    <Modal trigger="+ Kurikulum" title="Tambah Kurikulum">
      {(close) => <CreateForm close={close} />}
    </Modal>
  );
}

function CreateForm({ close }: { close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createKurikulum(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Kurikulum berhasil disimpan." });
  return (
    <form action={action} className="space-y-4">
      <KurikulumFields />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

export function EditKurikulumButton({ k }: { k: Kurikulum }) {
  return (
    <Modal trigger="Edit" title="Ubah Kurikulum" triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditForm k={k} close={close} />}
    </Modal>
  );
}

function EditForm({ k, close }: { k: Kurikulum; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateKurikulum(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Kurikulum berhasil diperbarui." });
  return (
    <form action={action} className="space-y-4">
      <KurikulumFields k={k} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <SubmitButton>Perbarui</SubmitButton>
      </div>
    </form>
  );
}

export function DeleteKurikulumButton({ k }: { k: Kurikulum }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Kurikulum" triggerVariant="danger" triggerSize="sm">
      {(close) => (
        <DeleteForm
          k={k}
          close={close}
          onDone={() => router.refresh()}
        />
      )}
    </Modal>
  );
}

function DeleteForm({ k, close, onDone }: { k: Kurikulum; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteKurikulum(fd), null);
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "Kurikulum dihapus." });
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={k.id} />
      <p className="text-sm text-muted">
        Hapus kurikulum <span className="font-medium text-ink">{k.nama}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <button type="submit" className={buttonClass("danger")}>
          Ya, hapus
        </button>
      </div>
    </form>
  );
}
