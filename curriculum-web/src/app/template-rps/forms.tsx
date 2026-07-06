"use client";

import { useActionState } from "react";
import { Modal, Field, TextAreaField, SubmitButton } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { TemplateRps, ApiResult } from "@/lib/api";
import { useActionResult } from "@/lib/use-action-result";
import { uploadTemplate, activateTemplate, updateTemplate, deleteTemplate } from "./actions";

type State = ApiResult | null;

/** Tombol + modal: unggah template baru. */
export function UploadTemplateButton() {
  return (
    <Modal trigger="Unggah Template" title="Unggah Template RPS" size="lg">
      {(close) => <UploadForm close={close} />}
    </Modal>
  );
}

function UploadForm({ close }: { close: () => void }) {
  const [state, action] = useActionState<State, FormData>(
    async (_prev, fd) => uploadTemplate(fd),
    null,
  );
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Template berhasil diunggah." });

  return (
    <form action={action} className="space-y-4">
      <p className="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-700">
        Unggah berkas format/template dokumen RPS (Word, Excel, HTML, atau PDF). Tandai satu sebagai
        aktif untuk dijadikan acuan cetak yang seragam.
      </p>
      <Field label="Nama template" name="nama" required placeholder="mis. Template RPS OBE 2026" />
      <TextAreaField
        label="Keterangan"
        name="keterangan"
        rows={3}
        placeholder="Catatan singkat: sumber, versi, atau pemakaian."
      />
      <label className="block">
        <span className="mb-1 block text-xs font-medium text-ink">
          Berkas template <span className="text-red-500">*</span>
        </span>
        <input
          name="berkas"
          type="file"
          required
          accept=".docx,.doc,.xlsx,.xls,.html,.htm,.pdf"
          className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring file:mr-3 file:rounded-md file:border-0 file:bg-brand-600 file:px-3 file:py-1 file:text-white"
        />
        <span className="mt-1 block text-xs text-muted">Maks. 20 MB — .docx, .xlsx, .html, .pdf</span>
      </label>
      <label className="flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" name="is_active" className="h-4 w-4 rounded border-border" />
        Jadikan template aktif
      </label>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <SubmitButton>Unggah</SubmitButton>
      </div>
    </form>
  );
}

/** Tombol: tandai template aktif. */
export function ActivateButton({ template }: { template: TemplateRps }) {
  const [state, action] = useActionState<State, FormData>(async (_p, fd) => activateTemplate(fd), null);
  useActionResult(state, { refresh: false, successMessage: "Template diaktifkan." });
  return (
    <form action={action} className="inline">
      <input type="hidden" name="id" value={template.id} />
      <button type="submit" className={buttonClass("secondary", "sm")}>
        Jadikan Aktif
      </button>
    </form>
  );
}

/** Tombol + modal: ubah metadata template. */
export function EditTemplateButton({ template }: { template: TemplateRps }) {
  return (
    <Modal trigger="Ubah" title={`Ubah — ${template.nama}`} triggerVariant="ghost" triggerSize="sm">
      {(close) => <EditForm template={template} close={close} />}
    </Modal>
  );
}

function EditForm({ template, close }: { template: TemplateRps; close: () => void }) {
  const [state, action] = useActionState<State, FormData>(
    async (_prev, fd) => updateTemplate(fd),
    null,
  );
  useActionResult(state, { refresh: false, onSuccess: close, successMessage: "Template diperbarui." });

  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={template.id} />
      <Field label="Nama template" name="nama" defaultValue={template.nama} required />
      <TextAreaField
        label="Keterangan"
        name="keterangan"
        defaultValue={template.keterangan ?? ""}
        rows={3}
      />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>
          Batal
        </button>
        <SubmitButton>Simpan</SubmitButton>
      </div>
    </form>
  );
}

/** Tombol: hapus template. */
export function DeleteTemplateButton({ template }: { template: TemplateRps }) {
  const [state, action] = useActionState<State, FormData>(async (_p, fd) => deleteTemplate(fd), null);
  useActionResult(state, { refresh: false, successMessage: "Template dihapus." });
  return (
    <form
      action={action}
      className="inline"
      onSubmit={(e) => {
        if (!confirm(`Hapus template "${template.nama}"?`)) e.preventDefault();
      }}
    >
      <input type="hidden" name="id" value={template.id} />
      <button type="submit" className={buttonClass("danger", "sm")}>
        Hapus
      </button>
    </form>
  );
}
