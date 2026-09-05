"use client";

import { useActionState, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, TextAreaField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import { useConfirm } from "@/components/confirm";
import type { PromptSlot, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createOverride, updateOverride, resetPrompt } from "./actions";

type State = ApiResult | null;

function contextLabel(slot: PromptSlot) {
  return `${slot.jenis_mk === "murni" ? "Teori (murni)" : slot.jenis_mk === "praktikum" ? "Praktikum" : "Semua jenis MK (umum)"} · ${slot.institusi_id == null ? "Global" : `Institusi #${slot.institusi_id}`}`;
}

/** Create a new version in the selected context, including after a reset. */
export function OverrideButton({ slot }: { slot: PromptSlot }) {
  return (
    <Modal trigger="Override prompt" title={`Override — ${slot.label}`} size="lg" triggerVariant="secondary" triggerSize="sm">
      {(close) => <OverrideForm slot={slot} close={close} edit={false} />}
    </Modal>
  );
}

function OverrideForm({ slot, close, edit }: { slot: PromptSlot; close: () => void; edit: boolean }) {
  const submitting = useRef(false);
  const [state, action, pending] = useActionState<State, FormData>(
    async (_prev, fd) => {
      try {
        return await (edit ? updateOverride(fd) : createOverride(fd));
      } catch {
        return { ok: false, status: 0, message: "Prompt belum tersimpan. Coba lagi." };
      } finally {
        submitting.current = false;
      }
    },
    null,
  );
  useActionResult(state, { onSuccess: close, successMessage: "Versi override prompt tersimpan." });

  return (
    <form action={action} className="space-y-4" onSubmit={(event) => {
      if (submitting.current) event.preventDefault();
      else submitting.current = true;
    }} aria-busy={pending}>
      {edit && <input type="hidden" name="id" value={slot.override!.id} />}
      <input type="hidden" name="jenis_output" value={slot.slot} />
      <input type="hidden" name="jenis_mk" value={slot.jenis_mk ?? ""} />
      <input type="hidden" name="institusi_id" value={slot.institusi_id ?? ""} />
      <p className="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-700">
        {contextLabel(slot)}. Simpan membuat versi aktif baru pada konteks ini; versi lama tetap menjadi riwayat.
      </p>
      <fieldset disabled={pending} className="space-y-4">
        <TextAreaField
          label="Prompt Sistem"
          name="sistem_prompt"
          defaultValue={edit ? slot.override!.sistem_prompt : slot.effective_system}
          required
          rows={7}
          hint={slot.default_schema ? "Wajib meminta balasan JSON valid sesuai skema." : "Slot ini memakai keluaran teks bebas, bukan JSON."}
        />
        <TextAreaField
          label="Skema Keluaran (JSON, opsional)"
          name="skema_output"
          defaultValue={edit ? (slot.override!.skema_output ?? "") : (slot.override?.skema_output ?? "")}
          mono
          rows={4}
          hint="Kosongkan untuk mengikuti skema default kode terbaru. Jika diisi, gunakan JSON dengan kunci akar dan tipe sesuai default."
        />
        {slot.default_schema && <details className="text-xs text-muted">
          <summary className="cursor-pointer">Lihat skema default</summary>
          <pre className="mt-2 overflow-auto whitespace-pre-wrap">{slot.default_schema}</pre>
        </details>}
        <p role="status" aria-live="polite" className="text-xs text-red-600">{state && !state.ok ? state.message : ""}</p>
        <div className="flex justify-end gap-2 pt-1">
          <button type="button" onClick={close} className={buttonClass("secondary")}>
            Batal
          </button>
          <button type="submit" disabled={pending} className={buttonClass("primary")}>
            {pending ? "Menyimpan…" : "Simpan Override"}
          </button>
        </div>
      </fieldset>
    </form>
  );
}

/** Tombol: ubah override yang sudah ada. */
export function EditOverrideButton({ slot }: { slot: PromptSlot }) {
  return (
    <Modal trigger="Ubah" title={`Ubah Override — ${slot.label}`} size="lg" triggerVariant="ghost" triggerSize="sm">
      {(close) => <OverrideForm slot={slot} close={close} edit />}
    </Modal>
  );
}

/** Explicit reset; guard covers both confirmation and the pending request. */
export function ResetOverrideButton({ slot }: { slot: PromptSlot }) {
  const router = useRouter();
  const toast = useToast();
  const { confirm } = useConfirm();
  const busy = useRef(false);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState("");

  async function reset() {
    if (busy.current) return;
    busy.current = true;
    setPending(true);
    setMessage("");
    try {
      if (!await confirm({
        title: "Kembalikan default",
        message: `${slot.label} — ${contextLabel(slot)}: gunakan default kode terbaru, bukan override lama/global. Riwayat disimpan. Slot, institusi, dan jenis MK lain tidak diubah.${slot.jenis_mk === null ? " Override khusus Teori/Praktikum tetap berlaku." : ""}`,
        confirmLabel: "Kembalikan default",
        tone: "danger",
      })) return;
      const fd = new FormData();
      fd.set("slot", slot.slot);
      fd.set("jenis_mk", slot.jenis_mk ?? "");
      fd.set("institusi_id", String(slot.institusi_id ?? ""));
      const res = await resetPrompt(fd);
      const feedback = res.ok ? "Prompt memakai default kode terbaru. Riwayat override tetap tersimpan." : (res.message ?? "Gagal mengembalikan default.");
      setMessage(feedback);
      toast({ type: res.ok ? "success" : "error", message: feedback });
      if (res.ok) router.refresh();
    } catch {
      setMessage("Gagal mengembalikan default. Muat ulang untuk memeriksa status sebelum mencoba lagi.");
    } finally {
      busy.current = false;
      setPending(false);
    }
  }

  return (
    <div aria-busy={pending}>
      <button
        type="button"
        className={buttonClass("danger", "sm")}
        disabled={pending || slot.sumber_efektif === "default"}
        onClick={reset}
      >
        {pending ? "Memproses…" : "Kembalikan default"}
      </button>
      <p role="status" aria-live="polite" className="mt-1 max-w-sm text-xs text-muted">{message}</p>
    </div>
  );
}
