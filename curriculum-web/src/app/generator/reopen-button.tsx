"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { useConfirm } from "@/components/confirm";
import { buttonClass } from "@/components/ui";
import { reopenSession } from "./actions";

export function ReopenButton({ sessionId, navigate = false }: { sessionId: number; navigate?: boolean }) {
  const { prompt } = useConfirm();
  const router = useRouter();
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);

  const reopen = async () => {
    const reason = await prompt({
      title: "Kembalikan RPS ke draf?",
      message: "Pengajuan tinjauan akan ditarik. Dokumen dan riwayat tetap tersimpan; perubahan baru masuk setelah commit ulang. Isi alasan pembukaan draf.",
      confirmLabel: "Kembalikan ke draf",
      placeholder: "Alasan perbaikan",
    });
    if (reason === null) return;
    if (!reason.trim()) { setError("Alasan wajib diisi."); return; }
    setError(null);
    start(async () => {
      const res = await reopenSession(sessionId, reason.trim());
      if (!res.ok) { setError(res.message ?? "Draf tidak dapat dibuka."); return; }
      if (navigate) router.push(`/generator/${sessionId}`);
      router.refresh();
    });
  };

  return <div>
    <button type="button" disabled={pending} onClick={reopen} className={buttonClass("secondary", "sm")}>
      {pending ? "Membuka draf…" : "Kembalikan ke draf"}
    </button>
    {error && <p role="alert" className="mt-1 text-xs text-red-700">{error}</p>}
  </div>;
}