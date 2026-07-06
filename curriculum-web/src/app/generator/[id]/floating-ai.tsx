"use client";

import { useState } from "react";
import { AiPanel } from "./ai-panel";

/**
 * Tombol awan AI mengambang (pojok kanan bawah). Diklik → panel konsultan AI
 * muncul sebagai kartu mengambang. Menegaskan prinsip co-pilot: AI hanya
 * dibuka saat dibutuhkan, penyusunan utama tetap manual di area edit.
 */
export function FloatingAiChat({ sessionId }: { sessionId: number }) {
  const [open, setOpen] = useState(false);

  return (
    <>
      {open && (
        <div className="fixed bottom-24 right-6 z-50 flex h-[min(600px,72vh)] w-[min(400px,92vw)] flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-2xl animate-fade-up">
          <div className="flex items-center justify-between border-b border-border bg-brand-600 px-4 py-3 text-white">
            <div className="flex items-center gap-2">
              <IconCloud />
              <span className="text-sm font-semibold">Asisten AI · Co-pilot</span>
            </div>
            <button
              type="button"
              onClick={() => setOpen(false)}
              className="grid h-7 w-7 place-items-center rounded-lg text-white/80 hover:bg-white/15 hover:text-white"
              aria-label="Tutup"
            >
              ✕
            </button>
          </div>
          <div className="min-h-0 flex-1">
            <AiPanel sessionId={sessionId} />
          </div>
        </div>
      )}

      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-label={open ? "Tutup asisten AI" : "Buka asisten AI"}
        className="fixed bottom-6 right-6 z-50 grid h-14 w-14 place-items-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700 hover:shadow-xl focus-ring"
      >
        {open ? <IconClose /> : <IconCloud />}
      </button>
    </>
  );
}

function IconCloud() {
  return (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17.5 19a4.5 4.5 0 0 0 .5-8.97A6 6 0 0 0 6.34 9.5 4 4 0 0 0 7 17.5" />
      <path d="M8.5 14.5 12 11l3.5 3.5" opacity="0" />
      <circle cx="9" cy="14" r="0.6" fill="currentColor" stroke="none" />
      <circle cx="12" cy="14" r="0.6" fill="currentColor" stroke="none" />
      <circle cx="15" cy="14" r="0.6" fill="currentColor" stroke="none" />
    </svg>
  );
}

function IconClose() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M6 6l12 12M18 6 6 18" />
    </svg>
  );
}
