"use client";

import { useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField } from "@/components/modal";
import { buttonClass, Spinner } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { BadanRujukan, ApiResult } from "@/lib/api";
import { uploadDokumen, reindexDokumen, deleteDokumen, createBadan, deleteBadan } from "./actions";

const JENIS_DOK_OPTS = [
  { value: "kpt", label: "Pedoman KPT" },
  { value: "asosiasi", label: "Rujukan Asosiasi" },
  { value: "akreditasi", label: "Kriteria Akreditasi" },
  { value: "template_rps", label: "Template RPS" },
];

const JENIS_BADAN_OPTS = [
  { value: "asosiasi", label: "Asosiasi" },
  { value: "akreditasi", label: "Lembaga Akreditasi" },
  { value: "pemerintah", label: "Pemerintah" },
  { value: "institusi", label: "Institusi" },
];

type State = ApiResult | null;

// Auto-refresh halaman selama masih ada dokumen berstatus "Menunggu" agar
// status indexing latar belakang terlihat berubah menjadi "Terindeks" tanpa
// perlu reload manual.
export function AutoRefreshWhilePending({ intervalMs = 4000 }: { intervalMs?: number }) {
  const router = useRouter();
  useEffect(() => {
    const t = setInterval(() => router.refresh(), intervalMs);
    return () => clearInterval(t);
  }, [router, intervalMs]);
  return null;
}

// ---- Upload dokumen ----
export function UploadDokumenButton({ badanList }: { badanList: BadanRujukan[] }) {
  return (
    <Modal trigger="+ Unggah Dokumen" title="Unggah Dokumen Rujukan">
      {(close) => <UploadForm badanList={badanList} close={close} />}
    </Modal>
  );
}

function UploadForm({ badanList, close }: { badanList: BadanRujukan[]; close: () => void }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => {
    const f = fd.get("file");
    if (f instanceof File && f.size > 50 * 1024 * 1024) {
      return { ok: false, status: 413, message: "Ukuran berkas melebihi 50MB. Perkecil ukuran PDF terlebih dahulu." };
    }
    setPending(true);
    const r = await uploadDokumen(fd);
    setPending(false);
    return r;
  }, null);
  useEffect(() => {
    if (!state) return;
    if (state.ok) {
      toast({ type: "success", message: "Dokumen diunggah. Indexing berjalan di latar belakang \u2014 status berubah menjadi Terindeks otomatis bila selesai." });
      router.refresh();
      close();
    } else {
      toast({ type: "error", message: state.message ?? "Gagal mengunggah dokumen." });
    }
  }, [state, close, router, toast]);

  const badanOpts = [{ value: "", label: "— Tanpa badan —" }, ...badanList.map((b) => ({ value: String(b.id), label: b.nama }))];

  return (
    <form action={action} className="space-y-3">
      <Field label="Judul" name="judul" placeholder="Panduan Penyusunan KPT 2020" />
      <div className="grid grid-cols-2 gap-3">
        <SelectField label="Jenis Dokumen" name="jenis" options={JENIS_DOK_OPTS} defaultValue="kpt" />
        <SelectField label="Badan Rujukan" name="badan_rujukan_id" options={badanOpts} />
      </div>
      <label className="block">
        <span className="mb-1 block text-xs font-medium text-ink">Berkas <span className="text-red-500">*</span></span>
        <input
          type="file"
          name="file"
          required
          accept=".pdf,.docx,.txt,.md,.csv"
          className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-sm"
        />
        <span className="mt-1 block text-xs text-muted">PDF, DOCX, TXT, MD, atau CSV (maks 50MB). Dokumen akan diindeks otomatis untuk grounding AI.</span>
      </label>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} disabled={pending} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending && <Spinner />}
          {pending ? "Mengunggah\u2026" : "Unggah & Indeks"}
        </button>
      </div>
    </form>
  );
}

export function ReindexButton({ id }: { id: number }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  return (
    <form
      action={async (fd) => {
        setPending(true);
        const r = await reindexDokumen(fd);
        setPending(false);
        if (r.ok) {
          toast({ type: "success", message: "Indexing ulang dijadwalkan. Status diperbarui otomatis bila selesai." });
          router.refresh();
        } else {
          toast({ type: "error", message: r.message ?? "Gagal mengindeks ulang." });
        }
      }}
      className="inline"
    >
      <input type="hidden" name="id" value={id} />
      <button type="submit" disabled={pending} className={buttonClass("ghost", "sm")}>
        {pending && <Spinner />}
        {pending ? "Memproses\u2026" : "Indeks ulang"}
      </button>
    </form>
  );
}

export function DeleteDokumenButton({ id, judul }: { id: number; judul: string }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Dokumen" triggerVariant="danger" triggerSize="sm">
      {(close) => (
        <DeleteInner
          id={id}
          judul={judul}
          action={deleteDokumen}
          close={close}
          onDone={() => router.refresh()}
        />
      )}
    </Modal>
  );
}

function DeleteInner({
  id,
  judul,
  action,
  close,
  onDone,
}: {
  id: number;
  judul: string;
  action: (fd: FormData) => Promise<ApiResult>;
  close: () => void;
  onDone: () => void;
}) {
  const [state, formAction] = useActionState<State, FormData>(async (_prev, fd) => action(fd), null);
  const toast = useToast();
  useEffect(() => {
    if (!state) return;
    if (state.ok) {
      toast({ type: "success", message: "Data berhasil dihapus." });
      onDone();
      close();
    } else {
      toast({ type: "error", message: state.message ?? "Gagal menghapus data." });
    }
  }, [state, close, onDone, toast]);
  return (
    <form action={formAction} className="space-y-4">
      <input type="hidden" name="id" value={id} />
      <p className="text-sm text-muted">
        Hapus <span className="font-medium text-ink">{judul}</span> beserta seluruh indeksnya?
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}

// ---- Badan Rujukan ----
export function CreateBadanButton() {
  return (
    <Modal trigger="+ Badan Rujukan" title="Tambah Badan Rujukan" triggerVariant="secondary">
      {(close) => <CreateBadanForm close={close} />}
    </Modal>
  );
}

function CreateBadanForm({ close }: { close: () => void }) {
  const router = useRouter();
  const toast = useToast();
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createBadan(fd), null);
  useEffect(() => {
    if (!state) return;
    if (state.ok) {
      toast({ type: "success", message: "Badan rujukan berhasil disimpan." });
      router.refresh();
      close();
    } else {
      toast({ type: "error", message: state.message ?? "Gagal menyimpan badan rujukan." });
    }
  }, [state, close, router, toast]);
  return (
    <form action={action} className="space-y-3">
      <Field label="Nama" name="nama" required placeholder="APTFI / LAM-PTKes / Kemdikbud" />
      <div className="grid grid-cols-2 gap-3">
        <SelectField label="Jenis" name="jenis" options={JENIS_BADAN_OPTS} defaultValue="asosiasi" />
        <Field label="Disiplin" name="disiplin" placeholder="Farmasi" />
      </div>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("primary")}>Simpan</button>
      </div>
    </form>
  );
}

export function DeleteBadanButton({ id, nama }: { id: number; nama: string }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Badan Rujukan" triggerVariant="danger" triggerSize="sm">
      {(close) => (
        <DeleteInner
          id={id}
          judul={nama}
          action={deleteBadan}
          close={close}
          onDone={() => router.refresh()}
        />
      )}
    </Modal>
  );
}
