"use client";

import { useState, useTransition, type ReactNode } from "react";
import type { MatriksProfilLulusan } from "@/lib/api";
import { EmptyState, buttonClass } from "@/components/ui";
import { toggleProfilLulusanLink, suggestProfilLulusanLinks } from "./actions";

export function ProfilLulusanCplMatrix({
  kurikulumId,
  matriks,
  title,
  subtitle,
}: {
  kurikulumId: number;
  matriks: MatriksProfilLulusan;
  title: string;
  subtitle?: string;
}) {
  const [links, setLinks] = useState<Set<string>>(
    () => new Set(matriks.links.map((l) => `${l.profil_lulusan_id}::${l.cpl_id}`)),
  );
  const [pending, setPending] = useState<Set<string>>(new Set());
  const [suggested, setSuggested] = useState<Set<string>>(new Set());
  const [aiBusy, setAiBusy] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);
  const [, startTransition] = useTransition();

  const empty = matriks.profil_lulusan.length === 0 || matriks.cpl.length === 0;

  function toggle(plId: number, cplId: number) {
    const key = `${plId}::${cplId}`;
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
      const res = await toggleProfilLulusanLink(kurikulumId, plId, cplId, active);
      if (!res.ok) {
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
    const res = await suggestProfilLulusanLinks(kurikulumId);
    setAiBusy(false);
    if (!res.ok || !res.data) {
      setAiError(res.message ?? "AI gagal memberi saran. Coba lagi.");
      return;
    }
    const next = new Set<string>();
    for (const l of res.data.links) {
      const key = `${l.profil_lulusan_id}::${l.cpl_id}`;
      if (!links.has(key)) next.add(key);
    }
    setSuggested(next);
    if (next.size === 0) setAiError("Tidak ada saran baru dari AI.");
  }

  function applyAll() {
    const items = [...suggested].map((k) => k.split("::").map(Number) as [number, number]);
    setSuggested(new Set());
    for (const [plId, cplId] of items) toggle(plId, cplId);
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
        <EmptyState title="Matriks belum lengkap" hint="Tambahkan profil lulusan & CPL terlebih dahulu." />
      ) : (
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
        <thead>
          <tr>
            <th className="sticky left-0 z-10 bg-gray-50/60 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted">
              Profil Lulusan
            </th>
            {matriks.cpl.map((c) => (
              <th key={c.id} className="px-2 py-2.5 text-center text-xs font-semibold text-muted">
                <span title={c.deskripsi}>{c.kode}</span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {matriks.profil_lulusan.map((pl) => (
            <tr key={pl.id} className="border-t border-border hover:bg-gray-50">
              <td className="sticky left-0 z-10 bg-surface px-4 py-2">
                <div className="min-w-[12rem]">
                  <p className="font-medium text-ink">{pl.kode}</p>
                  <p className="line-clamp-1 text-xs text-muted">{pl.deskripsi}</p>
                </div>
              </td>
              {matriks.cpl.map((c) => {
                const key = `${pl.id}::${c.id}`;
                const on = links.has(key);
                const sug = !on && suggested.has(key);
                const busy = pending.has(key);
                return (
                  <td key={c.id} className="px-2 py-2 text-center">
                    <button
                      type="button"
                      onClick={() => toggle(pl.id, c.id)}
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

type AiState = {
  busy: boolean;
  error: string | null;
  suggestedCount: number;
  onRun: () => void;
  onApply: () => void;
  onClear: () => void;
};

/**
 * Kerangka kartu matriks: judul + subjudul di kiri, tombol "✨ Saran AI" inline
 * di ujung kanan header. AI hanya mengusulkan (kotak kuning) — user tetap
 * pengambil keputusan (klik sel untuk menyetujui / "Terapkan semua" / "Batalkan").
 */
export function MatrixShell({
  title,
  subtitle,
  ai,
  empty,
  children,
}: {
  title: string;
  subtitle?: string;
  ai?: AiState;
  empty?: boolean;
  children: ReactNode;
}) {
  return (
    <div>
      <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-3.5">
        <div>
          <h2 className="text-sm font-semibold text-ink">{title}</h2>
          {subtitle && <p className="text-xs text-muted">{subtitle}</p>}
        </div>
        {ai && !empty && (
          <button
            type="button"
            onClick={ai.onRun}
            disabled={ai.busy}
            className={buttonClass("secondary", "sm") + " shrink-0 whitespace-nowrap"}
          >
            {ai.busy ? "AI menyusun saran…" : "✨ Saran AI"}
          </button>
        )}
      </div>
      {ai && !empty && (ai.suggestedCount > 0 || ai.error) && (
        <div className="border-b border-border px-5 py-2.5">
          {ai.suggestedCount > 0 && (
            <div className="flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
              <span>
                <b>{ai.suggestedCount}</b> saran AI (kotak kuning) belum disimpan. Anda yang memutuskan —
                klik sel untuk menyetujui satu per satu, atau:
              </span>
              <button type="button" onClick={ai.onApply} className={buttonClass("primary", "sm")}>
                Terapkan semua
              </button>
              <button type="button" onClick={ai.onClear} className={buttonClass("ghost", "sm")}>
                Batalkan
              </button>
            </div>
          )}
          {ai.error && <p className="mt-1 text-xs text-rose-600">{ai.error}</p>}
        </div>
      )}
      {children}
    </div>
  );
}
