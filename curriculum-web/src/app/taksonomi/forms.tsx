"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, TextAreaField, AiTextArea, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { Taksonomi, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createTaksonomi, updateTaksonomi, deleteTaksonomi } from "./actions";

type State = ApiResult | null;

const DOMAIN_OPTS = [
  { value: "kognitif", label: "Kognitif" },
  { value: "afektif", label: "Afektif" },
  { value: "psikomotorik", label: "Psikomotorik" },
];

const KERANGKA_OPTS = [
  { value: "bloom_anderson", label: "Bloom (Anderson)" },
  { value: "krathwohl", label: "Krathwohl" },
  { value: "dave", label: "Dave" },
  { value: "simpson", label: "Simpson" },
];

function TakFields({ t, defaults }: { t?: Taksonomi; defaults?: { domain: string; kerangka: string } }) {
  return (
    <div className="space-y-3">
      {t && <input type="hidden" name="id" value={t.id} />}
      <div className="grid grid-cols-2 gap-3">
        <SelectField label="Domain" name="domain" options={DOMAIN_OPTS} defaultValue={t?.domain ?? defaults?.domain ?? "kognitif"} required />
        <SelectField label="Kerangka" name="kerangka" options={KERANGKA_OPTS} defaultValue={t?.kerangka ?? defaults?.kerangka ?? "bloom_anderson"} required />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Kode" name="kode" defaultValue={t?.kode ?? ""} required placeholder="C1" />
        <Field label="Level" name="level" type="number" defaultValue={t?.level ?? ""} required placeholder="1" />
      </div>
      <Field label="Nama" name="nama" defaultValue={t?.nama ?? ""} required placeholder="Mengingat" />
      <AiTextArea label="Deskripsi" name="deskripsi" defaultValue={t?.deskripsi ?? ""} rows={2} placeholder="Ranah/level ini menuntut ..." konteks="Deskripsi level taksonomi" />
      <TextAreaField
        label="Kata kerja operasional"
        name="kata_kerja"
        defaultValue={(t?.kata_kerja ?? []).join(", ")}
        rows={2}
        placeholder="mendefinisikan, menyebutkan, mengidentifikasi"
        hint="Pisahkan dengan koma atau baris baru."
      />
    </div>
  );
}

export function CreateTaksonomiButton({ defaults }: { defaults?: { domain: string; kerangka: string } }) {
  return (
    <Modal trigger="+ Taksonomi" title="Tambah Taksonomi">
      {(close) => <CreateForm defaults={defaults} close={close} />}
    </Modal>
  );
}

function CreateForm({ defaults, close }: { defaults?: { domain: string; kerangka: string }; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createTaksonomi(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Taksonomi tersimpan." });
  return (
    <form action={action} className="space-y-4">
      <TakFields defaults={defaults} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

export function EditTaksonomiButton({ t }: { t: Taksonomi }) {
  return (
    <Modal trigger="Edit" title="Ubah Taksonomi" triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditForm t={t} close={close} />}
    </Modal>
  );
}

function EditForm({ t, close }: { t: Taksonomi; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateTaksonomi(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Taksonomi diperbarui." });
  return (
    <form action={action} className="space-y-4">
      <TakFields t={t} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Perbarui</SubmitButton>
      </div>
    </form>
  );
}

export function DeleteTaksonomiButton({ t }: { t: Taksonomi }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Taksonomi" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm t={t} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ t, close, onDone }: { t: Taksonomi; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteTaksonomi(fd), null);
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "Taksonomi dihapus." });
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={t.id} />
      <p className="text-sm text-ink">
        Hapus taksonomi <span className="font-semibold">{t.kode} — {t.nama}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Hapus</button>
      </div>
    </form>
  );
}
