"use client";

import { useCallback, useEffect, useRef, useState, type ReactNode } from "react";
import { buttonClass } from "./ui";
import { assistText, type AsistifMode } from "@/lib/ai-actions";

/**
 * Modal ringan berbasis <dialog>. Trigger sendiri (tombol) + isi children.
 * onClose dipanggil saat dialog ditutup (untuk mereset form bila perlu).
 */
export function Modal({
  trigger,
  title,
  children,
  triggerVariant = "primary",
  triggerSize = "md",
  size = "md",
}: {
  trigger: ReactNode;
  title: string;
  children: (close: () => void) => ReactNode;
  triggerVariant?: "primary" | "secondary" | "ghost" | "danger";
  triggerSize?: "sm" | "md";
  size?: "md" | "lg";
}) {
  const ref = useRef<HTMLDialogElement>(null);
  const [open, setOpen] = useState(false);

  const show = useCallback(() => setOpen(true), []);
  const close = useCallback(() => setOpen(false), []);

  // Sinkronkan state -> elemen <dialog> (showModal memberi backdrop modal).
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (open && !el.open) el.showModal();
    else if (!open && el.open) el.close();
  }, [open]);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const onCancel = () => setOpen(false);
    el.addEventListener("close", onCancel);
    return () => el.removeEventListener("close", onCancel);
  }, []);

  return (
    <>
      <button type="button" onClick={show} className={buttonClass(triggerVariant, triggerSize)}>
        {trigger}
      </button>
      <dialog
        ref={ref}
        className={`${size === "lg" ? "w-[min(56rem,94vw)]" : "w-[min(32rem,92vw)]"} fixed inset-0 m-auto h-fit max-h-[88vh] overflow-y-auto rounded-2xl border border-border bg-surface p-0 shadow-2xl backdrop:bg-black/40`}
      >
        {open && (
          <div className="animate-fade-up">
            <div className="flex items-center justify-between border-b border-border px-5 py-3.5">
              <h2 className="text-sm font-semibold text-ink">{title}</h2>
              <button
                type="button"
                onClick={close}
                className="grid h-7 w-7 place-items-center rounded-lg text-muted hover:bg-gray-100 hover:text-ink"
                aria-label="Tutup"
              >
                ✕
              </button>
            </div>
            <div className="p-5">{children(close)}</div>
          </div>
        )}
      </dialog>
    </>
  );
}

// ---- Field primitives (client) untuk dipakai di dalam Modal ----
export function Field({
  label,
  name,
  defaultValue = "",
  type = "text",
  required,
  placeholder,
  hint,
}: {
  label: string;
  name: string;
  defaultValue?: string | number;
  type?: string;
  required?: boolean;
  placeholder?: string;
  hint?: string;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-ink">
        {label} {required && <span className="text-red-500">*</span>}
      </span>
      <input
        name={name}
        type={type}
        defaultValue={defaultValue}
        required={required}
        placeholder={placeholder}
        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
      />
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
    </label>
  );
}

export function SelectField({
  label,
  name,
  options,
  defaultValue,
  required,
  onChange,
}: {
  label: string;
  name: string;
  options: { value: string; label: string }[];
  defaultValue?: string;
  required?: boolean;
  onChange?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-ink">
        {label} {required && <span className="text-red-500">*</span>}
      </span>
      <select
        name={name}
        defaultValue={defaultValue}
        required={required}
        onChange={onChange}
        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring"
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  );
}

export function TextAreaField({
  label,
  name,
  defaultValue = "",
  required,
  placeholder,
  hint,
  rows = 6,
  mono,
}: {
  label: string;
  name: string;
  defaultValue?: string;
  required?: boolean;
  placeholder?: string;
  hint?: string;
  rows?: number;
  mono?: boolean;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-ink">
        {label} {required && <span className="text-red-500">*</span>}
      </span>
      <textarea
        name={name}
        defaultValue={defaultValue}
        required={required}
        placeholder={placeholder}
        rows={rows}
        className={`w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400 ${
          mono ? "font-mono text-xs leading-relaxed" : ""
        }`}
      />
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
    </label>
  );
}

export function SubmitButton({ children }: { children: ReactNode }) {
  return (
    <button type="submit" className={buttonClass("primary", "md")}>
      {children}
    </button>
  );
}

// ---- AI-assisted textarea (asistif inline) ----
const AI_MODES: { mode: AsistifMode; label: string }[] = [
  { mode: "generate", label: "✨ Buat draf (generate)" },
  { mode: "perbaiki", label: "Perbaiki tata bahasa" },
  { mode: "parafrase", label: "Parafrase" },
  { mode: "ringkas", label: "Ringkas" },
  { mode: "panjangkan", label: "Perjelas / uraikan" },
];

/**
 * Textarea dengan tombol AI (✨). Field terkontrol: nilai tetap ikut form via
 * atribut `name`. AI membantu menyunting redaksi ATAU membuat draf baru
 * (mode "generate") berdasarkan field lain pada form (`konteksFields`).
 */
export function AiTextArea({
  label,
  name,
  defaultValue = "",
  required,
  placeholder,
  hint,
  rows = 4,
  konteks,
  konteksFields,
}: {
  label: string;
  name: string;
  defaultValue?: string;
  required?: boolean;
  placeholder?: string;
  hint?: string;
  rows?: number;
  konteks?: string;
  /** Nama+label input lain pada form yang dibaca sebagai fakta untuk mode generate. */
  konteksFields?: { name: string; label: string }[];
}) {
  const [value, setValue] = useState(defaultValue);
  const [menuOpen, setMenuOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const taRef = useRef<HTMLTextAreaElement>(null);

  // Kumpulkan nilai field lain pada form yang sama sebagai fakta konteks.
  const collectData = (): string => {
    const form = taRef.current?.form;
    if (!form || !konteksFields?.length) return "";
    return konteksFields
      .map(({ name: n, label: l }) => {
        const el = form.elements.namedItem(n) as HTMLInputElement | HTMLSelectElement | null;
        const v = el?.value?.trim();
        return v ? `${l}: ${v}` : null;
      })
      .filter(Boolean)
      .join("; ");
  };

  useEffect(() => {
    if (!menuOpen) return;
    const onDown = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
    };
    document.addEventListener("mousedown", onDown);
    return () => document.removeEventListener("mousedown", onDown);
  }, [menuOpen]);

  const runAi = async (mode: AsistifMode) => {
    setMenuOpen(false);
    setError(null);
    const data = mode === "generate" ? collectData() : undefined;
    if (mode === "generate") {
      if (!data && !value.trim()) {
        setError("Lengkapi dulu sebagian field (mis. Nama) agar AI punya konteks untuk membuat draf.");
        return;
      }
    } else if (!value.trim()) {
      setError("Isi teks terlebih dahulu sebelum meminta bantuan AI.");
      return;
    }
    setBusy(true);
    try {
      const res = await assistText({ mode, teks: value, konteks, data });
      if (res.ok && res.data) setValue(res.data.teks);
      else setError(res.message ?? "Layanan AI tidak tersedia saat ini.");
    } catch {
      setError("Gagal menghubungi layanan AI.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <label className="block">
      <div className="mb-1 flex items-center justify-between">
        <span className="text-xs font-medium text-ink">
          {label} {required && <span className="text-red-500">*</span>}
        </span>
        <div className="relative" ref={menuRef}>
          <button
            type="button"
            onClick={() => setMenuOpen((o) => !o)}
            disabled={busy}
            className="inline-flex items-center gap-1 rounded-md border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100 disabled:opacity-50"
          >
            {busy ? "Memproses…" : "✨ Bantu AI"}
          </button>
          {menuOpen && (
            <div className="absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-lg border border-border bg-surface py-1 shadow-lg">
              {AI_MODES.map((m) => (
                <button
                  key={m.mode}
                  type="button"
                  onClick={() => runAi(m.mode)}
                  className="block w-full px-3 py-1.5 text-left text-xs text-ink hover:bg-brand-50"
                >
                  {m.label}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
      <textarea
        ref={taRef}
        name={name}
        value={value}
        onChange={(e) => setValue(e.target.value)}
        required={required}
        placeholder={placeholder}
        rows={rows}
        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
      />
      {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
      {!error && hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
    </label>
  );
}
