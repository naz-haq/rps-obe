"use client";

import { useActionState, useRef } from "react";
import { Field } from "@/components/modal";
import { buttonClass, Spinner } from "@/components/ui";
import { useActionResult } from "@/lib/use-action-result";
import type { ApiResult } from "@/lib/api";
import { updateProfil, updatePassword } from "./actions";

type State = ApiResult | null;

export function ProfilForm({ nama, email }: { nama: string; email: string }) {
  const [state, action, pending] = useActionState<State, FormData>(
    async (_prev, fd) => updateProfil(fd),
    null,
  );
  useActionResult(state, { successMessage: "Profil berhasil diperbarui." });

  return (
    <form action={action} className="space-y-4">
      <Field label="Nama" name="name" required defaultValue={nama} />
      <Field
        label="Email"
        name="email"
        type="email"
        defaultValue={email}
        placeholder="nama@institusi.ac.id"
        hint="Email opsional; dipakai sebagai alternatif login selain NIDN."
      />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end">
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending && <Spinner />}
          {pending ? "Menyimpan\u2026" : "Simpan Perubahan"}
        </button>
      </div>
    </form>
  );
}

export function PasswordForm() {
  const formRef = useRef<HTMLFormElement>(null);
  const [state, action, pending] = useActionState<State, FormData>(
    async (_prev, fd) => updatePassword(fd),
    null,
  );
  useActionResult(state, {
    refresh: false,
    successMessage: "Kata sandi berhasil diperbarui.",
    onSuccess: () => formRef.current?.reset(),
  });

  return (
    <form ref={formRef} action={action} className="space-y-4">
      <Field label="Kata Sandi Saat Ini" name="current_password" type="password" required />
      <Field
        label="Kata Sandi Baru"
        name="password"
        type="password"
        required
        hint="Minimal 8 karakter."
      />
      <Field label="Konfirmasi Kata Sandi Baru" name="password_confirmation" type="password" required />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end">
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending && <Spinner />}
          {pending ? "Menyimpan\u2026" : "Ubah Kata Sandi"}
        </button>
      </div>
    </form>
  );
}
