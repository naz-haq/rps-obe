"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useId,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { buttonClass } from "./ui";

type ConfirmOpts = {
  title?: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  tone?: "danger" | "brand";
};
type PromptOpts = ConfirmOpts & { placeholder?: string; defaultValue?: string };

type ConfirmApi = {
  confirm: (opts: ConfirmOpts) => Promise<boolean>;
  prompt: (opts: PromptOpts) => Promise<string | null>;
};

const ConfirmContext = createContext<ConfirmApi>({
  confirm: async () => false,
  prompt: async () => null,
});

/** Dialog konfirmasi/isian dalam aplikasi (pengganti window.confirm/prompt native). */
export function useConfirm() {
  return useContext(ConfirmContext);
}

type State =
  | (PromptOpts & {
      kind: "confirm" | "prompt";
      resolve: (value: boolean | string | null) => void;
    })
  | null;

export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<State>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const confirmBtnRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const titleId = useId();
  const messageId = useId();

  const confirm = useCallback(
    (opts: ConfirmOpts) =>
      new Promise<boolean>((resolve) => {
        setState({ ...opts, kind: "confirm", resolve: (v) => resolve(Boolean(v)) });
      }),
    [],
  );

  const prompt = useCallback(
    (opts: PromptOpts) =>
      new Promise<string | null>((resolve) => {
        setState({ ...opts, kind: "prompt", resolve: (v) => resolve(v as string | null) });
      }),
    [],
  );

  const settle = useCallback((value: boolean | string | null) => {
    setState((s) => {
      s?.resolve(value);
      return null;
    });
  }, []);

  useEffect(() => {
    if (!state) return;
    previousFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    (state.kind === "prompt" ? inputRef.current : confirmBtnRef.current)?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") settle(state.kind === "prompt" ? null : false);
      if (e.key === "Tab") {
        const focusable = Array.from(
          dialogRef.current?.querySelectorAll<HTMLElement>(
            'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
          ) ?? [],
        );
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    window.addEventListener("keydown", onKey);
    return () => {
      window.removeEventListener("keydown", onKey);
      previousFocusRef.current?.focus();
    };
  }, [state, settle]);

  const cancelValue = state?.kind === "prompt" ? null : false;
  const acceptValue = () => (state?.kind === "prompt" ? inputRef.current?.value ?? "" : true);

  return (
    <ConfirmContext.Provider value={{ confirm, prompt }}>
      {children}
      {state && (
        <div
          className="fixed inset-0 z-[110] grid place-items-center bg-black/40 p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby={state.title ? titleId : undefined}
          aria-describedby={messageId}
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) settle(cancelValue);
          }}
        >
          <div ref={dialogRef} className="animate-fade-up max-h-[min(90vh,40rem)] w-[min(28rem,92vw)] overscroll-contain overflow-y-auto rounded-2xl border border-border bg-surface p-5 shadow-2xl motion-reduce:animate-none">
            {state.title && <h2 id={titleId} className="text-sm font-semibold text-ink">{state.title}</h2>}
            <p id={messageId} className="mt-1 text-sm text-muted">{state.message}</p>
            {state.kind === "prompt" && (
              <input
                ref={inputRef}
                aria-label={state.title ?? "Isian konfirmasi"}
                defaultValue={state.defaultValue}
                placeholder={state.placeholder}
                className="mt-3 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-brand-400 focus-visible:ring-2 focus-visible:ring-brand-200"
                onKeyDown={(e) => {
                  if (e.key === "Enter") settle(inputRef.current?.value ?? "");
                }}
              />
            )}
            <div className="mt-4 flex justify-end gap-2">
              <button type="button" className={buttonClass("ghost", "sm")} onClick={() => settle(cancelValue)}>
                {state.cancelLabel ?? "Batal"}
              </button>
              <button
                ref={confirmBtnRef}
                type="button"
                className={buttonClass(state.tone === "danger" ? "danger" : "primary", "sm")}
                onClick={() => settle(acceptValue())}
              >
                {state.confirmLabel ?? "Ya"}
              </button>
            </div>
          </div>
        </div>
      )}
    </ConfirmContext.Provider>
  );
}
