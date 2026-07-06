"use client";

import { useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { ApiResult, InstitusiData } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createInstitusi, updateInstitusi, deleteInstitusi } from "./actions";

type State = ApiResult | null;

type FakultasOpt = { id: number; nama: string };

const JENIS_OPTS = [
  { value: "prodi", label: "Program Studi" },
  { value: "fakultas", label: "Fakultas" },
];

function InstitusiFields({ item, fakultas }: { item?: InstitusiData; fakultas: FakultasOpt[] }) {
  const [jenis, setJenis] = useState<string>(item?.jenis ?? "prodi");
  const fakultasOpts = fakultas
    .filter((f) => f.id !== item?.id)
    .map((f) => ({ value: String(f.id), label: f.nama }));

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-3">
        <Field label="Nama" name="nama" required defaultValue={item?.nama ?? ""} placeholder="mis. S1 Farmasi" />
        <SelectField
          label="Jenis"
          name="jenis"
          options={JENIS_OPTS}
          defaultValue={item?.jenis ?? "prodi"}
          required
          onChange={(e) => setJenis(e.target.value)}
        />
      </div>
      {jenis === "prodi" && (
        <SelectField
          label="Fakultas Induk"
          name="parent_id"
          options={fakultasOpts}
          defaultValue={item?.parent_id ? String(item.parent_id) : ""}
          required
        />
      )}
      <div className="grid grid-cols-2 gap-3">
        <Field label="Kode" name="kode" defaultValue={item?.kode ?? ""} placeholder="Opsional, mis. FAR" />
        <Field
          label="Asosiasi Profesi"
          name="asosiasi_profesi"
          defaultValue={item?.asosiasi_profesi ?? ""}
          placeholder="mis. APTFI / IAI"
        />
      </div>
    </div>
  );
}

export function CreateInstitusiButton({ fakultas }: { fakultas: FakultasOpt[] }) {
  return (
    <Modal trigger="+ Tambah Prodi / Unit" title="Tambah Prodi / Unit">
      {(close) => <CreateForm close={close} fakultas={fakultas} />}
    </Modal>
  );
}

function CreateForm({ close, fakultas }: { close: () => void; fakultas: FakultasOpt[] }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createInstitusi(fd), null);
  useActionResult(state, { onSuccess: close, successMessage: "Prodi / unit tersimpan." });
  return (
    <form action={action} className="space-y-3">
      <InstitusiFields fakultas={fakultas} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("primary")}>Simpan</button>
      </div>
    </form>
  );
}

export function EditInstitusiButton({ item, fakultas }: { item: InstitusiData; fakultas: FakultasOpt[] }) {
  return (
    <Modal trigger="Ubah" title="Ubah Prodi / Unit" triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditForm item={item} close={close} fakultas={fakultas} />}
    </Modal>
  );
}

function EditForm({ item, close, fakultas }: { item: InstitusiData; close: () => void; fakultas: FakultasOpt[] }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateInstitusi(fd), null);
  useActionResult(state, { onSuccess: close, successMessage: "Prodi / unit diperbarui." });
  return (
    <form action={action} className="space-y-3">
      <input type="hidden" name="id" value={item.id} />
      <InstitusiFields item={item} fakultas={fakultas} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("primary")}>Simpan</button>
      </div>
    </form>
  );
}

export function DeleteInstitusiButton({ item }: { item: InstitusiData }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Prodi / Unit" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm item={item} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ item, close, onDone }: { item: InstitusiData; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteInstitusi(fd), null);
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "Prodi / unit dihapus." });
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={item.id} />
      <p className="text-sm text-muted">
        Hapus <span className="font-medium text-ink">{item.nama}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}
