"use client";

import { useActionState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, AiTextArea, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { BahanKajian, ApiResult } from "@/lib/api";
import { createBahanKajian, updateBahanKajian, deleteBahanKajian } from "./actions";

type State = ApiResult | null;

function BkFields({ kurikulumId, bk }: { kurikulumId: number; bk?: BahanKajian }) {
  return (
    <div className="space-y-3">
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      {bk && <input type="hidden" name="id" value={bk.id} />}
      <Field label="Nama" name="nama" defaultValue={bk?.nama ?? ""} required placeholder="Kimia Analisis" />
      <AiTextArea label="Deskripsi" name="deskripsi" defaultValue={bk?.deskripsi ?? ""} rows={4} placeholder="Cakupan bahan kajian ..." konteks="Deskripsi Bahan Kajian" />
    </div>
  );
}

export function CreateBahanKajianButton({ kurikulumId }: { kurikulumId: number }) {
  return (
    <Modal trigger="+ Bahan Kajian" title="Tambah Bahan Kajian">
      {(close) => <CreateForm kurikulumId={kurikulumId} close={close} />}
    </Modal>
  );
}

function CreateForm({ kurikulumId, close }: { kurikulumId: number; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createBahanKajian(fd), null);
  useEffect(() => {
    if (state?.ok) close();
  }, [state, close]);
  return (
    <form action={action} className="space-y-4">
      <BkFields kurikulumId={kurikulumId} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

export function EditBahanKajianButton({ bk, kurikulumId }: { bk: BahanKajian; kurikulumId: number }) {
  return (
    <Modal trigger="Edit" title="Ubah Bahan Kajian" triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditForm bk={bk} kurikulumId={kurikulumId} close={close} />}
    </Modal>
  );
}

function EditForm({ bk, kurikulumId, close }: { bk: BahanKajian; kurikulumId: number; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateBahanKajian(fd), null);
  useEffect(() => {
    if (state?.ok) close();
  }, [state, close]);
  return (
    <form action={action} className="space-y-4">
      <BkFields bk={bk} kurikulumId={kurikulumId} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Perbarui</SubmitButton>
      </div>
    </form>
  );
}

export function DeleteBahanKajianButton({ bk, kurikulumId }: { bk: BahanKajian; kurikulumId: number }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Bahan Kajian" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm bk={bk} kurikulumId={kurikulumId} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ bk, kurikulumId, close, onDone }: { bk: BahanKajian; kurikulumId: number; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteBahanKajian(fd), null);
  useEffect(() => {
    if (state?.ok) {
      onDone();
      close();
    }
  }, [state, close, onDone]);
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={bk.id} />
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      <p className="text-sm text-muted">
        Hapus bahan kajian <span className="font-medium text-ink">{bk.nama}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}
