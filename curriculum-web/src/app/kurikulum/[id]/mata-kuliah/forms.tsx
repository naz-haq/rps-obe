"use client";

import { useActionState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, AiTextArea, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { MataKuliah, ApiResult } from "@/lib/api";
import { createMataKuliah, updateMataKuliah, deleteMataKuliah } from "./actions";

const JENIS_OPTS = [
  { value: "", label: "— Pilih —" },
  { value: "murni", label: "Teori (murni)" },
  { value: "praktikum", label: "Praktikum" },
];

const SIFAT_OPTS = [
  { value: "", label: "— Pilih —" },
  { value: "wajib", label: "Wajib" },
  { value: "pilihan", label: "Pilihan" },
];

type State = ApiResult | null;

type ProdiOpt = { value: string; label: string };

function MkFields({ kurikulumId, m, prodiOptions }: { kurikulumId: number; m?: MataKuliah; prodiOptions: ProdiOpt[] }) {
  const prodiOpts =
    prodiOptions.length > 0
      ? [{ value: "", label: "— Pilih Prodi —" }, ...prodiOptions]
      : [{ value: "", label: "— Belum ada prodi, tambahkan di menu Prodi & Unit —" }];
  return (
    <div className="space-y-3">
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      {m && <input type="hidden" name="id" value={m.id} />}
      <SelectField
        label="Program Studi"
        name="institusi_id"
        options={prodiOpts}
        defaultValue={m?.institusi_id ? String(m.institusi_id) : ""}
        required
      />
      <div className="grid grid-cols-3 gap-3">
        <Field label="Kode MK" name="kode_mk" defaultValue={m?.kode_mk ?? ""} required placeholder="FAR101" />
        <div className="col-span-2">
          <Field label="Nama" name="nama" defaultValue={m?.nama ?? ""} required placeholder="Kimia Dasar" />
        </div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <SelectField label="Jenis" name="jenis_mk" options={JENIS_OPTS} defaultValue={m?.jenis_mk ?? ""} />
        <SelectField label="Sifat" name="sifat" options={SIFAT_OPTS} defaultValue={m?.sifat ?? ""} />
      </div>
      <div className="grid grid-cols-3 gap-3">
        <Field label="SKS Teori" name="sks_teori" type="number" defaultValue={m?.sks_teori ?? ""} placeholder="2" />
        <Field label="SKS Praktik" name="sks_praktik" type="number" defaultValue={m?.sks_praktik ?? ""} placeholder="1" />
        <Field label="Semester" name="semester" type="number" defaultValue={m?.semester ?? ""} placeholder="1" />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Rumpun" name="rumpun" defaultValue={m?.rumpun ?? ""} placeholder="Farmasi Klinik" />
        <Field label="Prasyarat (kode)" name="prasyarat_kode" defaultValue="" placeholder="FAR100" />
      </div>
      <AiTextArea label="Deskripsi Singkat" name="deskripsi_singkat" defaultValue={m?.deskripsi_singkat ?? ""} rows={3} placeholder="Ringkasan mata kuliah ..." konteks="Deskripsi Mata Kuliah" />
    </div>
  );
}

export function CreateMkButton({ kurikulumId, prodiOptions }: { kurikulumId: number; prodiOptions: ProdiOpt[] }) {
  return (
    <Modal trigger="+ Mata Kuliah" title="Tambah Mata Kuliah" size="lg">
      {(close) => <CreateForm kurikulumId={kurikulumId} prodiOptions={prodiOptions} close={close} />}
    </Modal>
  );
}

function CreateForm({ kurikulumId, prodiOptions, close }: { kurikulumId: number; prodiOptions: ProdiOpt[]; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createMataKuliah(fd), null);
  useEffect(() => {
    if (state?.ok) close();
  }, [state, close]);
  return (
    <form action={action} className="space-y-4">
      <MkFields kurikulumId={kurikulumId} prodiOptions={prodiOptions} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

export function EditMkButton({ m, kurikulumId, prodiOptions }: { m: MataKuliah; kurikulumId: number; prodiOptions: ProdiOpt[] }) {
  return (
    <Modal trigger="Edit" title="Ubah Mata Kuliah" triggerVariant="ghost" triggerSize="sm" size="lg">
      {(close) => <EditForm m={m} kurikulumId={kurikulumId} prodiOptions={prodiOptions} close={close} />}
    </Modal>
  );
}

function EditForm({ m, kurikulumId, prodiOptions, close }: { m: MataKuliah; kurikulumId: number; prodiOptions: ProdiOpt[]; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateMataKuliah(fd), null);
  useEffect(() => {
    if (state?.ok) close();
  }, [state, close]);
  return (
    <form action={action} className="space-y-4">
      <MkFields m={m} kurikulumId={kurikulumId} prodiOptions={prodiOptions} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Perbarui</SubmitButton>
      </div>
    </form>
  );
}

export function DeleteMkButton({ m, kurikulumId }: { m: MataKuliah; kurikulumId: number }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Mata Kuliah" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm m={m} kurikulumId={kurikulumId} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ m, kurikulumId, close, onDone }: { m: MataKuliah; kurikulumId: number; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteMataKuliah(fd), null);
  useEffect(() => {
    if (state?.ok) {
      onDone();
      close();
    }
  }, [state, close, onDone]);
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={m.id} />
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      <p className="text-sm text-muted">
        Hapus mata kuliah <span className="font-medium text-ink">{m.kode_mk} — {m.nama}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}
