"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField, TextAreaField, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { PromptSlot, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { createOverride, updateOverride, deleteOverride } from "./actions";

type State = ApiResult | null;

const JENIS_MK_OPTS = [
  { value: "", label: "Semua jenis MK" },
  { value: "murni", label: "Teori (murni)" },
  { value: "praktikum", label: "Praktikum" },
];

/** Tombol: buat override baru dari default slot. */
export function OverrideButton({ slot }: { slot: PromptSlot }) {
  return (
    <Modal trigger="Override prompt" title={`Override — ${slot.label}`} size="lg" triggerVariant="secondary" triggerSize="sm">
      {(close) => <OverrideForm slot={slot} close={close} />}
    </Modal>
  );
}

function OverrideForm({ slot, close }: { slot: PromptSlot; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(
    async (_prev, fd) => createOverride(fd),
    null,
  );
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Override prompt tersimpan." });

  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="jenis_output" value={slot.slot} />
      <p className="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-700">
        Prompt bawaan diisi otomatis sebagai titik awal. Simpan untuk membuat override; slot ini
        akan memakai teks di bawah alih-alih default.
      </p>
      <SelectField label="Berlaku untuk" name="jenis_mk" options={JENIS_MK_OPTS} defaultValue="" />
      <TextAreaField
        label="Prompt Sistem"
        name="sistem_prompt"
        defaultValue={slot.default_system}
        required
        rows={7}
        hint="Peran & instruksi untuk model. Wajib meminta balasan JSON valid sesuai skema."
      />
      <TextAreaField
        label="Skema Keluaran (JSON, opsional)"
        name="skema_output"
        defaultValue={slot.default_schema}
        mono
        rows={4}
        hint="Kosongkan untuk memakai skema default. Harus JSON valid bila diisi."
      />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <SubmitButton>Simpan Override</SubmitButton>
      </div>
    </form>
  );
}

/** Tombol: ubah override yang sudah ada. */
export function EditOverrideButton({ slot }: { slot: PromptSlot }) {
  return (
    <Modal trigger="Ubah" title={`Ubah Override — ${slot.label}`} size="lg" triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditOverrideForm slot={slot} close={close} />}
    </Modal>
  );
}

function EditOverrideForm({ slot, close }: { slot: PromptSlot; close: () => void }) {
  const ov = slot.override!;
  const [state, action] = useActionState<State, FormData>(
    async (_prev, fd) => updateOverride(fd),
    null,
  );
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Override prompt diperbarui." });

  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={ov.id} />
      <SelectField
        label="Berlaku untuk"
        name="jenis_mk"
        options={JENIS_MK_OPTS}
        defaultValue={ov.jenis_mk ?? ""}
      />
      <TextAreaField
        label="Prompt Sistem"
        name="sistem_prompt"
        defaultValue={ov.sistem_prompt}
        required
        rows={7}
      />
      <TextAreaField
        label="Skema Keluaran (JSON, opsional)"
        name="skema_output"
        defaultValue={ov.skema_output ?? ""}
        mono
        rows={4}
        hint="Kosongkan untuk memakai skema default."
      />
      <label className="flex items-center gap-2 text-xs font-medium text-ink">
        <input type="checkbox" name="aktif" defaultChecked={ov.aktif} className="h-4 w-4 rounded border-border" />
        Override aktif (nonaktifkan untuk sementara kembali ke default tanpa menghapus)
      </label>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <SubmitButton>Simpan Perubahan</SubmitButton>
      </div>
    </form>
  );
}

/** Tombol hapus override -> kembali default. */
export function ResetOverrideButton({ id }: { id: number }) {
  const router = useRouter();
  const toast = useToast();
  return (
    <form
      action={async (fd) => {
        const res = await deleteOverride(fd);
        if (res.ok) {
          toast({ type: "success", message: "Override dikembalikan ke default." });
          router.refresh();
        } else {
          toast({ type: "error", message: res.message ?? "Gagal mengembalikan default." });
        }
      }}
    >
      <input type="hidden" name="id" value={id} />
      <button
        type="submit"
        className={buttonClass("danger", "sm")}
        onClick={(e) => {
          if (!confirm("Hapus override dan kembali ke prompt default?")) e.preventDefault();
        }}
      >
        Kembalikan default
      </button>
    </form>
  );
}
