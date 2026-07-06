"use client";

import { useActionState, useEffect, useRef, useState } from "react";
import Script from "next/script";
import { buttonClass } from "@/components/ui";
import { loginAction, type LoginState } from "./actions";

type TurnstileApi = {
  render: (
    el: HTMLElement,
    options: {
      sitekey: string;
      callback: (token: string) => void;
      "expired-callback"?: () => void;
      "error-callback"?: () => void;
      theme?: "light" | "dark" | "auto";
      size?: "normal" | "flexible" | "compact";
    },
  ) => string;
};

declare global {
  interface Window {
    turnstile?: TurnstileApi;
  }
}

export function LoginForm({ turnstileSiteKey }: { turnstileSiteKey?: string }) {
  const [state, formAction, pending] = useActionState<LoginState, FormData>(loginAction, {});
  const [showPassword, setShowPassword] = useState(false);
  const [turnstileToken, setTurnstileToken] = useState("");

  const widgetRef = useRef<HTMLDivElement>(null);
  const renderedRef = useRef(false);

  useEffect(() => {
    if (!turnstileSiteKey) return;
    const tryRender = () => {
      if (renderedRef.current || !window.turnstile || !widgetRef.current) return;
      renderedRef.current = true;
      window.turnstile.render(widgetRef.current, {
        sitekey: turnstileSiteKey,
        theme: "light",
        size: "flexible",
        callback: (token) => setTurnstileToken(token),
        "expired-callback": () => setTurnstileToken(""),
        "error-callback": () => setTurnstileToken(""),
      });
    };
    tryRender();
    const id = window.setInterval(tryRender, 300);
    return () => window.clearInterval(id);
  }, [turnstileSiteKey]);

  const turnstileRequired = Boolean(turnstileSiteKey);
  const submitDisabled = pending || (turnstileRequired && !turnstileToken);

  return (
    <>
      {turnstileSiteKey && (
        <Script
          src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"
          strategy="afterInteractive"
        />
      )}
      <form action={formAction} className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">NIDN</span>
          <input
            name="login"
            type="text"
            autoComplete="username"
            required
            autoFocus
            defaultValue={state.login ?? ""}
            placeholder="Masukkan NIDN"
            className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
          />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Kata Sandi</span>
          <div className="relative">
            <input
              name="password"
              type={showPassword ? "text" : "password"}
              autoComplete="current-password"
              required
              placeholder="••••••••"
              className="w-full rounded-lg border border-border bg-surface px-3 py-2 pr-10 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
            />
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              aria-label={showPassword ? "Sembunyikan kata sandi" : "Lihat kata sandi"}
              title={showPassword ? "Sembunyikan kata sandi" : "Lihat kata sandi"}
              className="absolute inset-y-0 right-0 flex items-center px-3 text-muted hover:text-ink focus-ring rounded-r-lg"
            >
              {showPassword ? (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                  <line x1="1" y1="1" x2="23" y2="23" />
                </svg>
              ) : (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              )}
            </button>
          </div>
        </label>

        {turnstileSiteKey && (
          <div className="flex justify-center">
            <div ref={widgetRef} className="w-full" />
            <input type="hidden" name="cf-turnstile-response" value={turnstileToken} />
          </div>
        )}

        {state.error && (
          <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{state.error}</p>
        )}

        <button type="submit" disabled={submitDisabled} className={`${buttonClass("primary")} w-full justify-center`}>
          {pending ? "Memproses…" : "Masuk"}
        </button>

        <p className="text-center text-xs text-muted">Lupa password? Tanya admin.</p>
      </form>
    </>
  );
}
