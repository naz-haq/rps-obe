"use client";

import { useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import type { ProdiVmts } from "@/lib/api";
import { selectVmts } from "./vmts-actions";

export function VmtsManager({
  kurikulumId,
  currentVmtsId,
  versions,
}: {
  kurikulumId: string;
  currentVmtsId: number | null;
  versions: ProdiVmts[];
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();

  const pilih = (id: number | null) => {
    startTransition(async () => {
      await selectVmts(kurikulumId, id);
      router.refresh();
    });
  };

  return (
    <div className="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-gray-50/60 p-3">
      <label className="text-xs font-medium text-ink">Versi VMTS dipakai:</label>
      {versions.length === 0 ? (
        <span className="text-xs text-muted">
          Belum ada versi VMTS untuk prodi ini.{" "}
          <Link href="/prodi" className="font-medium text-brand-700 hover:underline">
            Buat di menu Prodi →
          </Link>
        </span>
      ) : (
        <>
          <select
            className="rounded-md border border-border bg-surface px-2 py-1 text-sm"
            value={currentVmtsId ?? ""}
            disabled={pending}
            onChange={(e) => pilih(e.target.value ? Number(e.target.value) : null)}
          >
            <option value="">— belum dipilih —</option>
            {versions.map((v) => (
              <option key={v.id} value={v.id}>
                {v.label}
              </option>
            ))}
          </select>
          {pending && <span className="text-xs text-muted">Menyimpan…</span>}
          <Link href="/prodi" className="text-xs text-brand-700 hover:underline">
            Kelola versi VMTS di menu Prodi →
          </Link>
        </>
      )}
    </div>
  );
}
