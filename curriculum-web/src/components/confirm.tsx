"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
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
    (state.kind === "prompt" ? inputRef.current : confirmBtnRef.current)?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") settle(state.kind === "prompt" ? null : false);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
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
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) settle(cancelValue);
          }}
        >
          <div className="animate-fade-up w-[min(28rem,92vw)] rounded-2xl border border-border bg-surface p-5 shadow-2xl">
            {state.title && <h2 className="text-sm font-semibold text-ink">{state.title}</h2>}
            <p className="mt-1 text-sm text-muted">{state.message}</p>
            {state.kind === "prompt" && (
              <input
                ref={inputRef}
                defaultValue={state.defaultValue}
                placeholder={state.placeholder}
                className="mt-3 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-brand-400"
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
