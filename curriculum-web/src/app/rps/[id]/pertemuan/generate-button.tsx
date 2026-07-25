"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { buttonClass } from "@/components/ui";
import { generateRincianPertemuan } from "../actions";

/**
 * Tombol generate lanjutan: memecah rencana mingguan RPS menjadi rincian
 * per-pertemuan lewat satu panggilan AI di backend.
 */
export function GeneratePertemuanButton({ id, ada }: { id: number; ada: boolean }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function jalankan() {
    setPending(true);
    setError(null);
    const r = await generateRincianPertemuan(id);
    setPending(false);
    if (r.ok) router.refresh();
    else setError(r.message ?? "Gagal menyusun rincian pertemuan.");
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <button
        type="button"
        disabled={pending}
        className={buttonClass("primary")}
        onClick={jalankan}
      >
        {pending
          ? "Menyusun rincian dengan AI…"
          : ada
            ? "Susun Ulang Rincian (AI)"
            : "Generate Rincian Pertemuan (AI)"}
      </button>
      {error && <p className="text-xs text-red-600">{error}</p>}
    </div>
  );
}
