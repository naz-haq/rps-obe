"use client";

import { useActionState, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, TextAreaField, AiTextArea, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { KerangkaAcuan, ButirAcuan, BadanRujukan, PemenuhanStatus, ApiResult } from "@/lib/api";
import {
  createKerangka,
  updateKerangka,
  deleteKerangka,
  createButir,
  updateButir,
  deleteButir,
  setPemenuhan,
} from "./actions";

type State = ApiResult | null;

const KATEGORI_OPTS = [
  { value: "profil_lulusan", label: "Profil Lulusan" },
  { value: "cpl", label: "CPL" },
  { value: "bahan_kajian", label: "Bahan Kajian" },
  { value: "kriteria_akreditasi", label: "Kriteria Akreditasi" },
  { value: "struktur", label: "Struktur" },
  { value: "aturan", label: "Aturan" },
];

const STATUS_OPTS: { value: PemenuhanStatus; label: string }[] = [
  { value: "belum", label: "Belum" },
  { value: "sebagian", label: "Sebagian" },
  { value: "terpenuhi", label: "Terpenuhi" },
  { value: "tidak_relevan", label: "Tidak relevan" },
];

// ================= Kerangka =================
function KerangkaFields({ k, badanList }: { k?: KerangkaAcuan; badanList: BadanRujukan[] }) {
  const badanOpts = badanList.map((b) => ({ value: String(b.id), label: b.nama }));
  return (
    <div className="space-y-3">
      {k && <input type="hidden" name="id" value={k.id} />}
      <Field label="Nama Kerangka" name="nama" defaultValue={k?.nama ?? ""} required placeholder="CPL Wajib APTFI 2021" />
      <div className="grid grid-cols-2 gap-3">
        <SelectField label="Badan Rujukan" name="badan_rujukan_id" options={badanOpts} defaultValue={k ? String(k.badan_rujukan_id) : undefined} required />
        <Field label="Versi" name="versi" defaultValue={k?.versi ?? ""} placeholder="2021" />
      </div>
      <Field label="Tanggal Berlaku" name="tanggal_berlaku" type="date" defaultValue={k?.tanggal_berlaku ?? ""} />
    </div>
  );
}

export function CreateKerangkaButton({ badanList }: { badanList: BadanRujukan[] }) {
  if (badanList.length === 0) {
    return (
      <a href="/dokumen-rujukan" className={buttonClass("secondary")}>
        Tambahkan Badan Rujukan dulu
      </a>
    );
  }
  return (
    <Modal trigger="+ Kerangka Acuan" title="Tambah Kerangka Acuan">
      {(close) => <SimpleForm action={createKerangka} close={close} submitLabel="Simpan"><KerangkaFields badanList={badanList} /></SimpleForm>}
    </Modal>
  );
}

export function EditKerangkaButton({ k, badanList }: { k: KerangkaAcuan; badanList: BadanRujukan[] }) {
  return (
    <Modal trigger="Edit" title="Ubah Kerangka Acuan" triggerVariant="ghost" triggerSize="sm">
      {(close) => <SimpleForm action={updateKerangka} close={close} submitLabel="Perbarui"><KerangkaFields k={k} badanList={badanList} /></SimpleForm>}
    </Modal>
  );
}

export function DeleteKerangkaButton({ k }: { k: KerangkaAcuan }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Kerangka Acuan" triggerVariant="danger" triggerSize="sm">
      {(close) => (
        <ConfirmDelete
          action={deleteKerangka}
          hidden={{ id: String(k.id) }}
          message={<>Hapus kerangka <span className="font-medium text-ink">{k.nama}</span> beserta seluruh butir & status pemenuhannya?</>}
          close={close}
          onDone={() => router.refresh()}
        />
      )}
    </Modal>
  );
}

// ================= Butir =================
function ButirFields({ b, kerangkaId }: { b?: ButirAcuan; kerangkaId: number }) {
  return (
    <div className="space-y-3">
      <input type="hidden" name="kerangka_id" value={kerangkaId} />
      {b && <input type="hidden" name="id" value={b.id} />}
      <div className="grid grid-cols-2 gap-3">
        <SelectField label="Kategori" name="kategori" options={KATEGORI_OPTS} defaultValue={b?.kategori ?? "cpl"} required />
        <Field label="Kode" name="kode" defaultValue={b?.kode ?? ""} placeholder="CPL-1" />
      </div>
      <AiTextArea label="Deskripsi Butir" name="deskripsi" defaultValue={b?.deskripsi ?? ""} required rows={3} placeholder="Mampu menguasai konsep ..." konteks="Butir/kriteria acuan capaian" />
      <label className="flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" name="wajib" defaultChecked={b ? b.wajib : true} className="h-4 w-4 rounded border-border" />
        Butir wajib dipenuhi
      </label>
    </div>
  );
}

export function CreateButirButton({ kerangkaId }: { kerangkaId: number }) {
  return (
    <Modal trigger="+ Butir" title="Tambah Butir Acuan">
      {(close) => <SimpleForm action={createButir} close={close} submitLabel="Simpan" refresh><ButirFields kerangkaId={kerangkaId} /></SimpleForm>}
    </Modal>
  );
}

export function EditButirButton({ b, kerangkaId }: { b: ButirAcuan; kerangkaId: number }) {
  return (
    <Modal trigger="Edit" title="Ubah Butir Acuan" triggerVariant="ghost" triggerSize="sm">
      {(close) => <SimpleForm action={updateButir} close={close} submitLabel="Perbarui" refresh><ButirFields b={b} kerangkaId={kerangkaId} /></SimpleForm>}
    </Modal>
  );
}

export function DeleteButirButton({ b, kerangkaId }: { b: ButirAcuan; kerangkaId: number }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Butir" triggerVariant="danger" triggerSize="sm">
      {(close) => (
        <ConfirmDelete
          action={deleteButir}
          hidden={{ id: String(b.id), kerangka_id: String(kerangkaId) }}
          message={<>Hapus butir <span className="font-medium text-ink">{b.kode ?? b.deskripsi.slice(0, 40)}</span>?</>}
          close={close}
          onDone={() => router.refresh()}
        />
      )}
    </Modal>
  );
}

// ================= Status pemenuhan (inline) =================
export function StatusControl({ b, kerangkaId }: { b: ButirAcuan; kerangkaId: number }) {
  const router = useRouter();
  const formRef = useRef<HTMLFormElement>(null);
  const [pending, setPending] = useState(false);

  return (
    <div className="flex items-center gap-2">
      <form
        ref={formRef}
        action={async (fd) => {
          setPending(true);
          await setPemenuhan(fd);
          setPending(false);
          router.refresh();
        }}
      >
        <input type="hidden" name="kerangka_id" value={kerangkaId} />
        <input type="hidden" name="butir_acuan_id" value={b.id} />
        <input type="hidden" name="catatan" value={b.catatan ?? ""} />
        <select
          name="status"
          defaultValue={b.status}
          disabled={pending}
          onChange={() => formRef.current?.requestSubmit()}
          className="rounded-lg border border-border bg-surface px-2 py-1 text-xs text-ink outline-none focus-ring"
        >
          {STATUS_OPTS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
      </form>
      <CatatanButton b={b} kerangkaId={kerangkaId} />
    </div>
  );
}

function CatatanButton({ b, kerangkaId }: { b: ButirAcuan; kerangkaId: number }) {
  const router = useRouter();
  return (
    <Modal trigger={b.catatan ? "Catatan ✓" : "Catatan"} title="Catatan Pemenuhan" triggerVariant="ghost" triggerSize="sm">
      {(close) => (
        <CatatanForm b={b} kerangkaId={kerangkaId} close={close} onDone={() => router.refresh()} />
      )}
    </Modal>
  );
}

function CatatanForm({ b, kerangkaId, close, onDone }: { b: ButirAcuan; kerangkaId: number; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => setPemenuhan(fd), null);
  useEffect(() => {
    if (state?.ok) {
      onDone();
      close();
    }
  }, [state, close, onDone]);
  return (
    <form action={action} className="space-y-3">
      <input type="hidden" name="kerangka_id" value={kerangkaId} />
      <input type="hidden" name="butir_acuan_id" value={b.id} />
      <input type="hidden" name="status" value={b.status} />
      <p className="text-xs text-muted">Butir: {b.kode ? `${b.kode} — ` : ""}{b.deskripsi}</p>
      <TextAreaField label="Catatan / Bukti Pemenuhan" name="catatan" defaultValue={b.catatan ?? ""} rows={4} placeholder="Mis. tercakup di CPL-01 & MK Farmakologi." />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

// ================= Helper generik =================
function SimpleForm({
  action,
  close,
  submitLabel,
  refresh,
  children,
}: {
  action: (fd: FormData) => Promise<ApiResult>;
  close: () => void;
  submitLabel: string;
  refresh?: boolean;
  children: React.ReactNode;
}) {
  const router = useRouter();
  const [state, formAction] = useActionState<State, FormData>(async (_prev, fd) => action(fd), null);
  useEffect(() => {
    if (state?.ok) {
      if (refresh) router.refresh();
      close();
    }
  }, [state, close, refresh, router]);
  return (
    <form action={formAction} className="space-y-4">
      {children}
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>{submitLabel}</SubmitButton>
      </div>
    </form>
  );
}

function ConfirmDelete({
  action,
  hidden,
  message,
  close,
  onDone,
}: {
  action: (fd: FormData) => Promise<ApiResult>;
  hidden: Record<string, string>;
  message: React.ReactNode;
  close: () => void;
  onDone: () => void;
}) {
  const [state, formAction] = useActionState<State, FormData>(async (_prev, fd) => action(fd), null);
  useEffect(() => {
    if (state?.ok) {
      onDone();
      close();
    }
  }, [state, close, onDone]);
  return (
    <form action={formAction} className="space-y-4">
      {Object.entries(hidden).map(([k, v]) => (
        <input key={k} type="hidden" name={k} value={v} />
      ))}
      <p className="text-sm text-muted">{message}</p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}
