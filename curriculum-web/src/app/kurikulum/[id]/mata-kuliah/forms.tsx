"use client";

import { useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, AiTextArea, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { MataKuliah, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createMataKuliah, updateMataKuliah, deleteMataKuliah } from "./actions";
import { ReferensiEditor } from "./referensi-editor";

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

const POLA_OPTS = [
  { value: "reguler", label: "Reguler (default pekan)" },
  { value: "blok", label: "Blok (durasi khusus)" },
  { value: "profesi", label: "Praktek Profesi / Klinik" },
];

type State = ApiResult | null;

type ProdiOpt = { value: string; label: string };

/** Aturan pekan efektif (dari KonfigurasiAturan) untuk pratinjau di form. */
export type AturanPekan = { mingguEfektif: number; mingguPerSks: number };

/**
 * Input Jumlah Pekan (override manual) + pratinjau nilai yang mengikuti ATURAN.
 * Cermin resolver backend jumlahMingguUntuk(): profesi = ceil(SKS × faktor),
 * lainnya = minggu efektif. Kosong ⇒ ikut aturan; terisi ⇒ menimpa aturan.
 */
function JumlahPekanField({
  m,
  pola,
  sksTotal,
  aturan,
}: {
  m?: MataKuliah;
  pola: string;
  sksTotal: number;
  aturan: AturanPekan;
}) {
  const [minggu, setMinggu] = useState(m?.jumlah_minggu != null ? String(m.jumlah_minggu) : "");

  const pekanAturan =
    pola === "profesi"
      ? Math.ceil(sksTotal * aturan.mingguPerSks) || aturan.mingguEfektif
      : aturan.mingguEfektif;

  const kosong = minggu.trim() === "";

  return (
    <label className="block">
      <div className="mb-1 flex items-center justify-between">
        <span className="text-xs font-medium text-ink">Jumlah Pekan</span>
        <button
          type="button"
          onClick={() => setMinggu(String(pekanAturan))}
          className="text-[11px] font-medium text-brand-700 hover:underline"
        >
          Isi sesuai aturan ({pekanAturan})
        </button>
      </div>
      <input
        name="jumlah_minggu"
        type="number"
        min={1}
        max={60}
        value={minggu}
        onChange={(e) => setMinggu(e.target.value)}
        placeholder={`kosong = ikut aturan (${pekanAturan})`}
        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
      />
      <span className="mt-1 block text-[11px] text-muted">
        {kosong
          ? `Kosong → mengikuti aturan: ${pekanAturan} pekan (pola ${pola}).`
          : `Override manual: ${minggu} pekan (menimpa aturan).`}
      </span>
    </label>
  );
}

function MkFields({ kurikulumId, m, prodiOptions, aturan }: { kurikulumId: number; m?: MataKuliah; prodiOptions: ProdiOpt[]; aturan: AturanPekan }) {
  const prodiOpts =
    prodiOptions.length > 0
      ? [{ value: "", label: "— Pilih Prodi —" }, ...prodiOptions]
      : [{ value: "", label: "— Belum ada prodi, tambahkan di menu Prodi & Unit —" }];
  const [pola, setPola] = useState<string>(m?.pola ?? "reguler");
  const [sksTeori, setSksTeori] = useState<number>(Number(m?.sks_teori ?? 0) || 0);
  const [sksPraktik, setSksPraktik] = useState<number>(Number(m?.sks_praktik ?? 0) || 0);
  const sksTotal = (Number.isFinite(sksTeori) ? sksTeori : 0) + (Number.isFinite(sksPraktik) ? sksPraktik : 0);
  return (
    <div className="space-y-3">
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      {m && <input type="hidden" name="id" value={m.id} />}
      {m && <input type="hidden" name="kode_mk_lama" value={m.kode_mk} />}
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
      <div className="grid grid-cols-2 gap-3">
        <SelectField
          label="Pola Pelaksanaan"
          name="pola"
          options={POLA_OPTS}
          defaultValue={m?.pola ?? "reguler"}
          onChange={(e) => setPola(e.target.value)}
        />
        <JumlahPekanField m={m} pola={pola} sksTotal={sksTotal} aturan={aturan} />
      </div>
      <div className="grid grid-cols-3 gap-3">
        <Field label="SKS Teori" name="sks_teori" type="number" defaultValue={m?.sks_teori ?? ""} placeholder="2" onChange={(e) => setSksTeori(Number(e.target.value) || 0)} />
        <Field label="SKS Praktik" name="sks_praktik" type="number" defaultValue={m?.sks_praktik ?? ""} placeholder="1" onChange={(e) => setSksPraktik(Number(e.target.value) || 0)} />
        <Field label="Semester" name="semester" type="number" defaultValue={m?.semester ?? ""} placeholder="1" />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Rumpun" name="rumpun" defaultValue={m?.rumpun ?? ""} placeholder="Farmasi Klinik" />
        <Field label="Prasyarat (kode)" name="prasyarat_kode" defaultValue="" placeholder="FAR100" />
      </div>
      <AiTextArea
        label="Deskripsi Singkat"
        name="deskripsi_singkat"
        defaultValue={m?.deskripsi_singkat ?? ""}
        rows={3}
        placeholder="Ringkasan mata kuliah ..."
        konteks="Deskripsi Mata Kuliah"
        konteksFields={[
          { name: "nama", label: "Nama MK" },
          { name: "kode_mk", label: "Kode" },
          { name: "jenis_mk", label: "Jenis" },
          { name: "sks_teori", label: "SKS Teori" },
          { name: "sks_praktik", label: "SKS Praktik" },
          { name: "rumpun", label: "Rumpun" },
        ]}
      />
      <ReferensiEditor mk={m} />
    </div>
  );
}

export function CreateMkButton({ kurikulumId, prodiOptions, aturan }: { kurikulumId: number; prodiOptions: ProdiOpt[]; aturan: AturanPekan }) {
  return (
    <Modal trigger="+ Mata Kuliah" title="Tambah Mata Kuliah" size="lg">
      {(close) => <CreateForm kurikulumId={kurikulumId} prodiOptions={prodiOptions} aturan={aturan} close={close} />}
    </Modal>
  );
}

function CreateForm({ kurikulumId, prodiOptions, aturan, close }: { kurikulumId: number; prodiOptions: ProdiOpt[]; aturan: AturanPekan; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createMataKuliah(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Mata kuliah tersimpan." });
  return (
    <form action={action} className="space-y-4">
      <MkFields kurikulumId={kurikulumId} prodiOptions={prodiOptions} aturan={aturan} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

export function EditMkButton({ m, kurikulumId, prodiOptions, aturan }: { m: MataKuliah; kurikulumId: number; prodiOptions: ProdiOpt[]; aturan: AturanPekan }) {
  return (
    <Modal trigger="Edit" title="Ubah Mata Kuliah" triggerVariant="ghost" triggerSize="sm" size="lg">
      {(close) => <EditForm m={m} kurikulumId={kurikulumId} prodiOptions={prodiOptions} aturan={aturan} close={close} />}
    </Modal>
  );
}

function EditForm({ m, kurikulumId, prodiOptions, aturan, close }: { m: MataKuliah; kurikulumId: number; prodiOptions: ProdiOpt[]; aturan: AturanPekan; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateMataKuliah(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Mata kuliah diperbarui." });
  return (
    <form action={action} className="space-y-4">
      <MkFields m={m} kurikulumId={kurikulumId} prodiOptions={prodiOptions} aturan={aturan} />
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
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "Mata kuliah dihapus." });
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
