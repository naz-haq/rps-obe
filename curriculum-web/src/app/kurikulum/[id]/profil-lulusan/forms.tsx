"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, AiTextArea, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { ProfilLulusan, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createProfil, updateProfil, deleteProfil } from "./actions";

type State = ApiResult | null;

function ProfilFields({ kurikulumId, p }: { kurikulumId: number; p?: ProfilLulusan }) {
  return (
    <div className="space-y-3">
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      {p && <input type="hidden" name="id" value={p.id} />}
      <Field label="Kode" name="kode" defaultValue={p?.kode ?? ""} required placeholder="PL-01" />
      <AiTextArea label="Deskripsi" name="deskripsi" defaultValue={p?.deskripsi ?? ""} required rows={4} placeholder="Lulusan yang mampu ..." konteks="Deskripsi Profil Lulusan" />
    </div>
  );
}

export function CreateProfilButton({ kurikulumId }: { kurikulumId: number }) {
  return (
    <Modal trigger="+ Profil Lulusan" title="Tambah Profil Lulusan">
      {(close) => <CreateForm kurikulumId={kurikulumId} close={close} />}
    </Modal>
  );
}

function CreateForm({ kurikulumId, close }: { kurikulumId: number; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createProfil(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Profil lulusan tersimpan." });
  return (
    <form action={action} className="space-y-4">
      <ProfilFields kurikulumId={kurikulumId} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

export function EditProfilButton({ p, kurikulumId }: { p: ProfilLulusan; kurikulumId: number }) {
  return (
    <Modal trigger="Edit" title="Ubah Profil Lulusan" triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditForm p={p} kurikulumId={kurikulumId} close={close} />}
    </Modal>
  );
}

function EditForm({ p, kurikulumId, close }: { p: ProfilLulusan; kurikulumId: number; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateProfil(fd), null);
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Profil lulusan diperbarui." });
  return (
    <form action={action} className="space-y-4">
      <ProfilFields p={p} kurikulumId={kurikulumId} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <SubmitButton>Perbarui</SubmitButton>
      </div>
    </form>
  );
}

export function DeleteProfilButton({ p, kurikulumId }: { p: ProfilLulusan; kurikulumId: number }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Profil Lulusan" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm p={p} kurikulumId={kurikulumId} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ p, kurikulumId, close, onDone }: { p: ProfilLulusan; kurikulumId: number; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteProfil(fd), null);
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "Profil lulusan dihapus." });
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={p.id} />
      <input type="hidden" name="kurikulum_id" value={kurikulumId} />
      <p className="text-sm text-muted">
        Hapus profil <span className="font-medium text-ink">{p.kode}</span>? Tindakan ini tidak dapat dibatalkan.
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}
