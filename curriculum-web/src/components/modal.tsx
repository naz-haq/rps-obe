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
  onChange,
}: {
  label: string;
  name: string;
  defaultValue?: string | number;
  type?: string;
  required?: boolean;
  placeholder?: string;
  hint?: string;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
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
        onChange={onChange}
        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
      />
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
    </label>
  );
}

export type SearchableOption = { value: string; label: string; group?: string; disabled?: boolean };

/**
 * Dropdown dengan kotak pencarian — pengganti <select> untuk daftar panjang.
 * Mendukung terkontrol (value+onChange) maupun form-post (name+defaultValue).
 */
export function SearchableSelect({
  options,
  value,
  defaultValue,
  onChange,
  name,
  placeholder = "— Pilih —",
  required,
  size = "md",
  className = "",
  displayValue,
  allowClear = true,
}: {
  options: SearchableOption[];
  value?: string;
  defaultValue?: string;
  onChange?: (value: string) => void;
  name?: string;
  placeholder?: string;
  required?: boolean;
  size?: "sm" | "md";
  className?: string;
  /** Teks tombol saat nilai terpilih tak ada di daftar opsi. */
  displayValue?: string;
  allowClear?: boolean;
}) {
  const controlled = value !== undefined;
  const [internal, setInternal] = useState(defaultValue ?? "");
  const val = controlled ? value : internal;
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [hi, setHi] = useState(0);
  const boxRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  const current = options.find((o) => o.value === val);
  const tokens = q.toLowerCase().split(/\s+/).filter(Boolean);
  const filtered = options.filter((o) =>
    tokens.every((t) => `${o.label} ${o.value} ${o.group ?? ""}`.toLowerCase().includes(t)),
  );

  useEffect(() => {
    if (!open) return;
    const t = setTimeout(() => searchRef.current?.focus(), 0);
    const onDown = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onDown);
    return () => {
      clearTimeout(t);
      document.removeEventListener("mousedown", onDown);
    };
  }, [open]);

  const toggle = (next: boolean) => {
    if (next) {
      setQ("");
      setHi(0);
    }
    setOpen(next);
  };

  const pick = (v: string) => {
    if (!controlled) setInternal(v);
    onChange?.(v);
    setOpen(false);
  };

  const move = (dir: 1 | -1) => {
    const enabled = filtered.map((o, i) => (o.disabled ? -1 : i)).filter((i) => i >= 0);
    if (enabled.length === 0) return;
    const pos = enabled.indexOf(hi);
    const next = pos === -1 ? enabled[0] : enabled[(pos + dir + enabled.length) % enabled.length];
    setHi(next);
  };

  const btnCls =
    size === "sm"
      ? "px-2.5 py-1.5 text-xs"
      : "px-3 py-2 text-sm";

  return (
    <div ref={boxRef} className={`relative ${className}`}>
      <button
        type="button"
        onClick={() => toggle(!open)}
        aria-haspopup="listbox"
        aria-expanded={open}
        className={`flex w-full items-center justify-between gap-2 rounded-lg border border-border bg-surface text-left text-ink outline-none focus-ring ${btnCls}`}
      >
        <span className={`truncate ${current || val ? "" : "text-gray-400"}`}>
          {current?.label ?? (val ? displayValue ?? val : placeholder)}
        </span>
        <span aria-hidden className="shrink-0 text-[10px] text-gray-400">▼</span>
      </button>
      {name && (
        <input
          tabIndex={-1}
          aria-hidden
          name={name}
          required={required}
          value={val}
          onChange={() => {}}
          onFocus={() => toggle(true)}
          className="pointer-events-none absolute inset-x-0 bottom-0 h-px w-full opacity-0"
        />
      )}
      {open && (
        <div className="absolute z-40 mt-1 w-full min-w-[15rem] overflow-hidden rounded-lg border border-border bg-surface shadow-xl">
          <div className="border-b border-border p-1.5">
            <input
              ref={searchRef}
              type="search"
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                setHi(0);
              }}
              onKeyDown={(e) => {
                if (e.key === "ArrowDown") { e.preventDefault(); move(1); }
                else if (e.key === "ArrowUp") { e.preventDefault(); move(-1); }
                else if (e.key === "Enter") {
                  e.preventDefault();
                  const o = filtered[hi];
                  if (o && !o.disabled) pick(o.value);
                } else if (e.key === "Escape") { e.preventDefault(); setOpen(false); }
              }}
              placeholder="Ketik untuk mencari…"
              className="w-full rounded-md border border-border bg-surface px-2 py-1.5 text-xs text-ink outline-none focus:border-brand-400"
            />
          </div>
          <ul role="listbox" className="max-h-60 overflow-y-auto p-1">
            {allowClear && !required && !q && (
              <li>
                <button
                  type="button"
                  onClick={() => pick("")}
                  className="w-full rounded-md px-2.5 py-1.5 text-left text-xs text-muted hover:bg-gray-100"
                >
                  {placeholder}
                </button>
              </li>
            )}
            {filtered.length === 0 && (
              <li className="px-2.5 py-2 text-xs text-muted">Tidak ada yang cocok.</li>
            )}
            {filtered.map((o, i) => {
              const header = o.group && (i === 0 || filtered[i - 1].group !== o.group);
              return (
                <li key={`${o.value}-${i}`}>
                  {header && (
                    <p className="px-2.5 pb-0.5 pt-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                      {o.group}
                    </p>
                  )}
                  <button
                    type="button"
                    disabled={o.disabled}
                    onMouseEnter={() => setHi(i)}
                    onClick={() => pick(o.value)}
                    className={`w-full rounded-md px-2.5 py-1.5 text-left text-xs ${
                      o.disabled
                        ? "cursor-not-allowed text-gray-300"
                        : i === hi
                          ? "bg-brand-50 text-brand-700"
                          : "text-ink hover:bg-gray-100"
                    } ${o.value === val ? "font-semibold" : ""}`}
                  >
                    {o.label}
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      )}
    </div>
  );
}

export function SelectField({
  label,
  name,
  options,
  defaultValue,
  required,
  onChange,
  hint,
}: {
  label: string;
  name: string;
  options: { value: string; label: string }[];
  defaultValue?: string;
  required?: boolean;
  onChange?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
  hint?: string;
}) {
  // Daftar panjang otomatis memakai dropdown ber-pencarian.
  const searchable = options.length > 8;
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-ink">
        {label} {required && <span className="text-red-500">*</span>}
      </span>
      {searchable ? (
        <SearchableSelect
          name={name}
          options={options}
          defaultValue={defaultValue ?? (required ? "" : options[0]?.value)}
          required={required}
          placeholder="— Pilih —"
          allowClear={false}
          onChange={(v) =>
            onChange?.({ target: { value: v } } as React.ChangeEvent<HTMLSelectElement>)
          }
        />
      ) : (
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
      )}
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
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
            className={buttonClass("ai", "xs")}
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
