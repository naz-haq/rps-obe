"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Modal } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { useActionResult } from "@/lib/use-action-result";
import { rpsStatusLabel } from "@/lib/rps-status";
import type { RpsVersion, ApiResult } from "@/lib/api";
import { deleteRpsVersion } from "./actions";

type State = ApiResult | null;

export function DeleteRpsButton({ rps }: { rps: RpsVersion }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Dokumen RPS" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm rps={rps} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ rps, close, onDone }: { rps: RpsVersion; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteRpsVersion(fd), null);
  useActionResult(state, { refresh: false, onSuccess: () => { onDone(); close(); }, successMessage: "Dokumen RPS dihapus." });
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={rps.id} />
      <p className="text-sm text-muted">
        Hapus dokumen RPS <span className="font-medium text-ink">{rps.kode_mk} v{rps.versi}</span>{" "}
        (status {rpsStatusLabel(rps.status)})? Seluruh rencana mingguan dan komponen penilaian pada versi ini
        ikut terhapus. Tindakan ini tidak dapat dibatalkan.
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
