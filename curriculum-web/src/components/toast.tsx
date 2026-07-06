"use client";

import { createContext, useCallback, useContext, useState, type ReactNode } from "react";

export type ToastType = "success" | "error" | "warning" | "info";
type ToastInput = { type?: ToastType; message: string; title?: string; duration?: number };
type ToastItem = { id: number; type: ToastType; message: string; title?: string };

const ToastContext = createContext<(t: ToastInput) => void>(() => {});

/** Hook untuk memunculkan notifikasi toast dari komponen klien mana pun. */
export function useToast() {
  return useContext(ToastContext);
}

let counter = 0;

const TONE: Record<ToastType, { ring: string; icon: string; iconBg: string }> = {
  success: { ring: "border-l-emerald-500", icon: "✓", iconBg: "bg-emerald-100 text-emerald-700" },
  error: { ring: "border-l-red-500", icon: "!", iconBg: "bg-red-100 text-red-700" },
  warning: { ring: "border-l-amber-500", icon: "!", iconBg: "bg-amber-100 text-amber-700" },
  info: { ring: "border-l-brand-500", icon: "i", iconBg: "bg-brand-100 text-brand-700" },
};

const DEFAULT_TITLE: Record<ToastType, string> = {
  success: "Berhasil",
  error: "Gagal",
  warning: "Perhatian",
  info: "Informasi",
};

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);

  const remove = useCallback((id: number) => {
    setItems((list) => list.filter((t) => t.id !== id));
  }, []);

  const toast = useCallback(
    (t: ToastInput) => {
      const id = ++counter;
      const type = t.type ?? "info";
      setItems((list) => [...list, { id, type, message: t.message, title: t.title }]);
      const duration = t.duration ?? 4500;
      window.setTimeout(() => remove(id), duration);
    },
    [remove],
  );

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div className="pointer-events-none fixed right-4 top-4 z-[100] flex w-[min(22rem,92vw)] flex-col gap-2">
        {items.map((t) => {
          const tone = TONE[t.type];
          return (
            <div
              key={t.id}
              role="status"
              className={`animate-fade-up pointer-events-auto flex items-start gap-3 rounded-xl border border-border ${tone.ring} border-l-4 bg-surface p-3 shadow-lg`}
            >
              <span className={`mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold ${tone.iconBg}`}>
                {tone.icon}
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-ink">{t.title ?? DEFAULT_TITLE[t.type]}</p>
                <p className="mt-0.5 break-words text-xs text-muted">{t.message}</p>
              </div>
              <button
                type="button"
                onClick={() => remove(t.id)}
                aria-label="Tutup notifikasi"
                className="grid h-6 w-6 shrink-0 place-items-center rounded-lg text-muted hover:bg-gray-100 hover:text-ink"
              >
                ✕
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}
