"use client";

import { useState, useTransition } from "react";
import type { MatriksMkBahanKajian } from "@/lib/api";
import { EmptyState } from "@/components/ui";
import { toggleMkBahanKajianLink, suggestMkBahanKajianLinks } from "./actions";
import { MatrixShell } from "../profil-lulusan/matriks";

/**
 * Matriks Bahan Kajian × Mata Kuliah interaktif — klik sel untuk menandai bahan
 * kajian mana yang dibungkus tiap mata kuliah. Acuan peninjauan struktur: bahan
 * kajian yang belum tertaut satu MK pun ditandai "yatim" (⚠) agar bisa ditinjau.
 * Tombol ✨ Saran AI mengusulkan tautan (kotak kuning) — user tetap memutuskan.
 */
export function MkBahanKajianMatrix({
  kurikulumId,
  matriks,
  title,
  subtitle,
}: {
  kurikulumId: number;
  matriks: MatriksMkBahanKajian;
  title: string;
  subtitle?: string;
}) {
  const [links, setLinks] = useState<Set<string>>(
    () => new Set(matriks.links.map((l) => `${l.kode_mk}::${l.bahan_kajian_id}`)),
  );
  const [pending, setPending] = useState<Set<string>>(new Set());
  const [suggested, setSuggested] = useState<Set<string>>(new Set());
  const [aiBusy, setAiBusy] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);
  const [, startTransition] = useTransition();

  const empty = matriks.mata_kuliah.length === 0 || matriks.bahan_kajian.length === 0;

  function toggle(kodeMk: string, bkId: number) {
    const key = `${kodeMk}::${bkId}`;
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
      const res = await toggleMkBahanKajianLink(kurikulumId, kodeMk, bkId, active);
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
    const res = await suggestMkBahanKajianLinks(kurikulumId);
    setAiBusy(false);
    if (!res.ok || !res.data) {
      setAiError(res.message ?? "AI gagal memberi saran. Coba lagi.");
      return;
    }
    const next = new Set<string>();
    for (const l of res.data.links) {
      const key = `${l.kode_mk}::${l.bahan_kajian_id}`;
      if (!links.has(key)) next.add(key);
    }
    setSuggested(next);
    if (next.size === 0) setAiError("Tidak ada saran baru dari AI.");
  }

  function applyAll() {
    const items = [...suggested].map((k) => {
      const [kodeMk, bkId] = k.split("::");
      return [kodeMk, Number(bkId)] as [string, number];
    });
    setSuggested(new Set());
    for (const [kodeMk, bkId] of items) toggle(kodeMk, bkId);
  }

  function clearSuggested() {
    setSuggested(new Set());
    setAiError(null);
  }

  const bkTertaut = new Set([...links].map((k) => Number(k.split("::")[1])));

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
        <EmptyState
          title="Matriks belum lengkap"
          hint="Tambahkan mata kuliah & bahan kajian terlebih dahulu."
        />
      ) : (
      <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr>
            <th className="sticky left-0 z-10 bg-gray-50/60 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted">
              Mata Kuliah
            </th>
            {matriks.bahan_kajian.map((bk) => {
              const yatim = !bkTertaut.has(bk.id);
              return (
                <th key={bk.id} className="px-2 py-2.5 text-center text-xs font-semibold">
                  <span
                    title={
                      yatim
                        ? `${bk.nama} — belum tertaut mata kuliah (yatim)`
                        : (bk.deskripsi ?? bk.nama)
                    }
                    className={yatim ? "text-rose-600" : "text-muted"}
                  >
                    {bk.nama}
                    {yatim && <span className="ml-0.5" aria-hidden>⚠</span>}
                  </span>
                </th>
              );
            })}
          </tr>
        </thead>
        <tbody>
          {matriks.mata_kuliah.map((mk) => (
            <tr key={mk.id} className="border-t border-border hover:bg-gray-50">
              <td className="sticky left-0 z-10 bg-surface px-4 py-2">
                <div className="min-w-[12rem]">
                  <p className="font-medium text-ink">{mk.kode_mk}</p>
                  <p className="text-xs text-muted">{mk.nama}</p>
                </div>
              </td>
              {matriks.bahan_kajian.map((bk) => {
                const key = `${mk.kode_mk}::${bk.id}`;
                const on = links.has(key);
                const sug = !on && suggested.has(key);
                const busy = pending.has(key);
                return (
                  <td key={bk.id} className="px-2 py-2 text-center">
                    <button
                      type="button"
                      onClick={() => toggle(mk.kode_mk, bk.id)}
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
