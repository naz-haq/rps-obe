"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Modal } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { useActionResult } from "@/lib/use-action-result";
import type { GenerateSession, ApiResult } from "@/lib/api";
import { deleteSession } from "./actions";

type State = ApiResult | null;

export function DeleteSessionButton({ sesi }: { sesi: GenerateSession }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Sesi Penyusunan" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm sesi={sesi} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ sesi, close, onDone }: { sesi: GenerateSession; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteSession(fd), null);
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "Sesi penyusunan dihapus." });
  const label = sesi.kode_mk ?? `MK #${sesi.mk_id}`;
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={sesi.id} />
      <p className="text-sm text-muted">
        Hapus sesi penyusunan <span className="font-medium text-ink">{label}</span>? Draf tahap yang belum
        dikomit akan hilang. {sesi.status === "committed" ? "RPS yang sudah dikomit tetap tersimpan dan tidak ikut terhapus." : ""}
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
