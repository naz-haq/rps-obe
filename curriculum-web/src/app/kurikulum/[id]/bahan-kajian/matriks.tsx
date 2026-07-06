"use client";

import { useState, useTransition } from "react";
import type { MatriksBahanKajian } from "@/lib/api";
import { EmptyState } from "@/components/ui";
import { toggleBahanKajianLink, suggestBahanKajianLinks } from "./actions";
import { MatrixShell } from "../profil-lulusan/matriks";

export function CplBahanKajianMatrix({
  kurikulumId,
  matriks,
  title,
  subtitle,
}: {
  kurikulumId: number;
  matriks: MatriksBahanKajian;
  title: string;
  subtitle?: string;
}) {
  const [links, setLinks] = useState<Set<string>>(
    () => new Set(matriks.links.map((l) => `${l.cpl_id}::${l.bahan_kajian_id}`)),
  );
  const [pending, setPending] = useState<Set<string>>(new Set());
  const [suggested, setSuggested] = useState<Set<string>>(new Set());
  const [aiBusy, setAiBusy] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);
  const [, startTransition] = useTransition();

  const empty = matriks.bahan_kajian.length === 0 || matriks.cpl.length === 0;

  function toggle(cplId: number, bkId: number) {
    const key = `${cplId}::${bkId}`;
    const active = !links.has(key);

    setLinks((prev) => {
      const next = new Set(prev);
      if (active) next.add(key);
      else next.delete(key);
      return next;
    });
    setSuggested((prev) => {
      if (!prev.has(key)) return prev;
      const next = new Set(prev);
      next.delete(key);
      return next;
    });
    setPending((prev) => new Set(prev).add(key));

    startTransition(async () => {
      const res = await toggleBahanKajianLink(kurikulumId, cplId, bkId, active);
      if (!res.ok) {
        // rollback saat gagal
        setLinks((prev) => {
          const next = new Set(prev);
          if (active) next.delete(key);
          else next.add(key);
          return next;
        });
      }
      setPending((prev) => {
        const next = new Set(prev);
        next.delete(key);
        return next;
      });
    });
  }

  async function runAi() {
    setAiBusy(true);
    setAiError(null);
    const res = await suggestBahanKajianLinks(kurikulumId);
    setAiBusy(false);
    if (!res.ok || !res.data) {
      setAiError(res.message ?? "AI gagal memberi saran. Coba lagi.");
      return;
    }
    const next = new Set<string>();
    for (const l of res.data.links) {
      const key = `${l.cpl_id}::${l.bahan_kajian_id}`;
      if (!links.has(key)) next.add(key);
    }
    setSuggested(next);
    if (next.size === 0) setAiError("Tidak ada saran baru dari AI.");
  }

  function applyAll() {
    const items = [...suggested].map((k) => k.split("::").map(Number) as [number, number]);
    setSuggested(new Set());
    for (const [cplId, bkId] of items) toggle(cplId, bkId);
  }

  function clearSuggested() {
    setSuggested(new Set());
    setAiError(null);
  }

  return (
    <MatrixShell
      title={title}
      subtitle={subtitle}
      empty={empty}
      ai={{
        busy: aiBusy,
        error: aiError,
        suggestedCount: suggested.size,
        onRun: runAi,
        onApply: applyAll,
        onClear: clearSuggested,
      }}
    >
      {empty ? (
        <EmptyState title="Matriks belum lengkap" hint="Tambahkan bahan kajian & CPL terlebih dahulu." />
      ) : (
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
        <thead>
          <tr>
            <th className="sticky left-0 z-10 bg-gray-50/60 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted">
              Bahan Kajian
            </th>
            {matriks.cpl.map((c) => (
              <th key={c.id} className="px-2 py-2.5 text-center text-xs font-semibold text-muted">
                <span title={c.deskripsi}>{c.kode}</span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {matriks.bahan_kajian.map((bk) => (
            <tr key={bk.id} className="border-t border-border hover:bg-gray-50">
              <td className="sticky left-0 z-10 bg-surface px-4 py-2">
                <div className="min-w-[12rem]">
                  <p className="font-medium text-ink">{bk.nama}</p>
                  {bk.deskripsi && <p className="line-clamp-1 text-xs text-muted">{bk.deskripsi}</p>}
                </div>
              </td>
              {matriks.cpl.map((c) => {
                const key = `${c.id}::${bk.id}`;
                const on = links.has(key);
                const sug = !on && suggested.has(key);
                const busy = pending.has(key);
                return (
                  <td key={c.id} className="px-2 py-2 text-center">
                    <button
                      type="button"
                      onClick={() => toggle(c.id, bk.id)}
                      disabled={busy}
                      aria-pressed={on}
                      title={
                        sug
                          ? "Saran AI — klik untuk menyetujui"
                          : on
                            ? "Klik untuk melepas tautan"
                            : "Klik untuk menautkan"
                      }
                      className={`mx-auto grid h-6 w-6 place-items-center rounded-md border transition ${
                        on
                          ? "border-brand-600 bg-brand-600 text-white"
                          : sug
                            ? "border-amber-400 bg-amber-100 text-amber-600"
                            : "border-border bg-white text-transparent hover:border-brand-400 hover:bg-brand-50"
                      } ${busy ? "opacity-50" : ""}`}
                    >
                      <svg viewBox="0 0 20 20" fill="currentColor" className="h-3.5 w-3.5">
                        <path
                          fillRule="evenodd"
                          d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.3 3.29 6.8-6.8a1 1 0 0 1 1.4 0Z"
                          clipRule="evenodd"
                        />
                      </svg>
                    </button>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
        </table>
      </div>
      )}
    </MatrixShell>
  );
}
