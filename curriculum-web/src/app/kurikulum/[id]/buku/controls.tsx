"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { generateNaratifBuku } from "./actions";

const BACKEND_PROXY = "/backend/api/v1";

export function BukuControls({
  kurikulumId,
  sudahAdaNaratif,
  naratifPada,
}: {
  kurikulumId: string;
  sudahAdaNaratif: boolean;
  naratifPada: string | null;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [pesan, setPesan] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const jalankan = () => {
    setPesan(null);
    setError(null);
    startTransition(async () => {
      try {
        const res = await generateNaratifBuku(kurikulumId);
        setPesan(res.message);
        router.refresh();
      } catch {
        setError("Gagal membuat narasi. Coba lagi beberapa saat.");
      }
    });
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={jalankan}
          disabled={pending}
          className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
        >
          {pending
            ? "Membuat narasi…"
            : sudahAdaNaratif
              ? "Regenerate Narasi (AI)"
              : "Generate Narasi (AI)"}
        </button>
        <a
          href={`${BACKEND_PROXY}/kurikulum/${kurikulumId}/buku/docx`}
          className="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-ink hover:bg-gray-50"
        >
          Unduh DOCX
        </a>
        {naratifPada && (
          <span className="text-xs text-muted">
            Narasi terakhir: {new Date(naratifPada).toLocaleString("id-ID")}
          </span>
        )}
      </div>
      {pending && (
        <p className="text-xs text-muted">
          Menyusun narasi via AI — proses ini bisa memakan waktu hingga beberapa menit bila layanan AI sedang sibuk.
        </p>
      )}
      {pesan && <p className="text-xs text-emerald-700">{pesan}</p>}
      {error && <p className="text-xs text-red-700">{error}</p>}
    </div>
  );
}
