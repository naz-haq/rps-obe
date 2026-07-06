"use client";

import { useActionState } from "react";
import { buttonClass } from "@/components/ui";
import { Logo } from "@/components/branding";
import { branding } from "@/lib/branding";
import { loginAction, type LoginState } from "./actions";

export default function LoginPage() {
  const [state, formAction, pending] = useActionState<LoginState, FormData>(loginAction, {});

  return (
    <div className="grid min-h-screen place-items-center bg-gray-50 px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex flex-col items-center text-center">
          <Logo size={72} className="mb-3" />
          <h1 className="text-lg font-semibold text-ink">{branding.appName}</h1>
          <p className="text-sm text-muted">Masuk untuk melanjutkan ke {branding.appName}.</p>
        </div>

        <form action={formAction} className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-ink">NIDN</span>
            <input
              name="login"
              type="text"
              autoComplete="username"
              required
              autoFocus
              placeholder="Masukkan NIDN"
              className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-ink">Kata Sandi</span>
            <input
              name="password"
              type="password"
              autoComplete="current-password"
              required
              placeholder="••••••••"
              className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
            />
          </label>

          {state.error && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{state.error}</p>
          )}

          <button type="submit" disabled={pending} className={`${buttonClass("primary")} w-full justify-center`}>
            {pending ? "Memproses…" : "Masuk"}
          </button>
        </form>

        <p className="mt-4 text-center text-xs text-muted">
          {branding.institution} · {branding.footer.text}
        </p>
      </div>
    </div>
  );
}
