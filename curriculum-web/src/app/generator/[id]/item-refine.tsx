"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { ItemCandidate } from "@/lib/api";
import { regenerateItem, applyItem, pinItem } from "../actions";

const ACTIONS: { v: string; l: string }[] = [
  { v: "perbaiki_redaksi", l: "Perbaiki redaksi" },
  { v: "buat_alternatif", l: "Buat alternatif" },
  { v: "naikkan_taksonomi", l: "Naikkan taksonomi" },
  { v: "periksa_konsistensi", l: "Periksa konsistensi" },
  { v: "perkaya", l: "Perkaya nilai" },
];

const LABEL: Record<string, string> = {
  kode: "Kode",
  deskripsi: "Rumusan",
  cpl_kode: "CPL",
  cpmk_kode: "CPMK induk",
  sub_cpmk_kode: "Sub-CPMK",
  taksonomi_kode: "Taksonomi",
  indikator: "Indikator",
  kriteria_penilaian: "Kriteria & bentuk",
  metode_pembelajaran: "Metode",
  bentuk_luring: "Luring",
  bentuk_daring: "Daring",
  pengalaman_belajar: "Penugasan",
  materi_pustaka: "Materi/pustaka",
  bobot_penilaian: "Bobot",
  nama: "Nama",
  jenis: "Jenis",
  instrumen: "Instrumen",
  bobot_persen: "Bobot (%)",
  minggu_ke: "Minggu",
};

function fmt(v: unknown): string {
  if (v == null) return "—";
  if (Array.isArray(v)) return v.join(", ");
  if (typeof v === "object") return JSON.stringify(v);
  return String(v);
}

function DiffView({ before, after }: { before: Record<string, unknown>; after: Record<string, unknown> }) {
  const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)])).filter(
    (k) => !k.startsWith("_") && k !== "rubrik",
  );
  const changed = keys.filter((k) => fmt(before[k]) !== fmt(after[k]));
  const unchanged = keys.filter((k) => fmt(before[k]) === fmt(after[k]));
  if (changed.length === 0) {
    return <p className="text-xs text-muted">AI tidak mengusulkan perubahan berarti pada item ini.</p>;
  }
  return (
    <div className="space-y-2">
      {changed.map((k) => (
        <div key={k} className="overflow-hidden rounded-md border border-border text-xs">
          <div className="border-b border-border bg-gray-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted">
            {LABEL[k] ?? k}
          </div>
          <div className="bg-rose-50 px-2 py-1.5 text-rose-700 line-through">{fmt(before[k])}</div>
          <div className="border-t border-border bg-emerald-50 px-2 py-1.5 text-emerald-800">{fmt(after[k])}</div>
        </div>
      ))}
      {unchanged.length > 0 && (
        <p className="text-[11px] text-muted">Tetap: {unchanged.map((k) => LABEL[k] ?? k).join(", ")}.</p>
      )}
    </div>
  );
}

export function ItemRefine({
  sessionId,
  stage,
  itemId,
  pinned = false,
  needsReview = false,
  baseRevisi,
}: {
  sessionId: number;
  stage: string;
  itemId?: string;
  pinned?: boolean;
  needsReview?: boolean;
  baseRevisi: number;
}) {
  const router = useRouter();
  const toast = useToast();
  const [open, setOpen] = useState(false);
  const [action, setAction] = useState("perbaiki_redaksi");
  const [instruction, setInstruction] = useState("");
  const [cand, setCand] = useState<ItemCandidate | null>(null);
  const [pending, start] = useTransition();

  if (!itemId) return null;

  const buatUsulan = () =>
    start(async () => {
      setCand(null);
      const r = await regenerateItem(sessionId, stage, itemId, { action, instruction: instruction.trim() || undefined });
      if (!r.ok || !r.candidate) {
        toast({ type: "error", message: r.message ?? "Gagal membuat usulan." });
        return;
      }
      setCand(r.candidate);
    });

  const terapkan = () =>
    start(async () => {
      if (!cand) return;
      const r = await applyItem(sessionId, stage, itemId, cand.after, cand.base_revisi);
      if (!r.ok) {
        toast({
          type: r.status === 409 ? "warning" : "error",
          message: r.message ?? "Gagal menerapkan usulan.",
        });
        return;
      }
      toast({ type: "success", message: "Usulan diterapkan. Versi sebelumnya tercatat." });
      setCand(null);
      setOpen(false);
      router.refresh();
    });

  const togglePin = () =>
    start(async () => {
      const r = await pinItem(sessionId, stage, itemId, !pinned);
      if (!r.ok) {
        toast({ type: "error", message: r.message ?? "Gagal mengubah sematan." });
        return;
      }
      router.refresh();
    });

  return (
    <div className="mt-1.5">
      <div className="flex flex-wrap items-center gap-1.5">
        <button
          type="button"
          disabled={pending || pinned}
          onClick={() => setOpen((v) => !v)}
          className={buttonClass("secondary", "sm")}
          title={pinned ? "Lepas sematan dulu untuk memperbaiki" : "Perbaiki item ini dengan AI"}
        >
          ✨ Perbaiki
        </button>
        <button
          type="button"
          disabled={pending}
          onClick={togglePin}
          className={buttonClass(pinned ? "primary" : "ghost", "sm")}
          title={pinned ? "Item disematkan — klik untuk melepas" : "Sematkan agar tak berubah"}
        >
          {pinned ? "📌 Tersemat" : "Sematkan"}
        </button>
        {needsReview && (
          <span className="rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
            Perlu ditinjau
          </span>
        )}
      </div>

      {open && !pinned && (
        <div className="mt-2 rounded-lg border border-brand-200 bg-brand-50/40 p-3">
          <div className="flex flex-wrap gap-1.5">
            {ACTIONS.map((a) => (
              <button
                key={a.v}
                type="button"
                onClick={() => setAction(a.v)}
                className={`rounded-md border px-2 py-1 text-[11px] font-medium ${
                  action === a.v
                    ? "border-brand-400 bg-brand-100 text-brand-800"
                    : "border-border bg-surface text-gray-600 hover:bg-gray-50"
                }`}
              >
                {a.l}
              </button>
            ))}
          </div>
          <textarea
            value={instruction}
            onChange={(e) => setInstruction(e.target.value)}
            placeholder="Instruksi tambahan (opsional), mis. buat lebih terukur…"
            className="mt-2 w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs outline-none focus:border-brand-400"
            rows={2}
          />
          <div className="mt-2 flex items-center gap-2">
            <button type="button" disabled={pending} onClick={buatUsulan} className={buttonClass("primary", "sm")}>
              {pending && !cand ? "Menyusun…" : "Buat usulan"}
            </button>
            <span className="text-[10px] text-muted">Usulan tidak langsung mengubah draf.</span>
          </div>

          {cand && (
            <div className="mt-3 border-t border-border pt-3">
              <p className="mb-1.5 text-[11px] font-semibold text-ink">Pratinjau perubahan</p>
              <DiffView before={cand.before} after={cand.after} />
              {cand.base_revisi !== baseRevisi && (
                <p className="mt-2 rounded-md bg-amber-50 px-2 py-1 text-[11px] text-amber-700">
                  Draf berubah sejak usulan dibuat. Buat usulan ulang agar tidak menimpa perubahan terbaru.
                </p>
              )}
              <div className="mt-2 flex items-center gap-2">
                <button
                  type="button"
                  disabled={pending || cand.base_revisi !== baseRevisi}
                  onClick={terapkan}
                  className={buttonClass("primary", "sm")}
                >
                  Terapkan
                </button>
                <button type="button" disabled={pending} onClick={() => setCand(null)} className={buttonClass("ghost", "sm")}>
                  Tolak
                </button>
                {cand.usage?.estimated_usd != null && (
                  <span className="text-[10px] text-muted">
                    {cand.usage.model ?? "AI"} · ~${cand.usage.estimated_usd}
                  </span>
                )}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
