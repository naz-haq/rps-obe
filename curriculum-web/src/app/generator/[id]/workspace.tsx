"use client";

// Ruang kerja generator sesuai prototipe (§8): daftar item dengan checkbox
// multi-pilih + toolbar aksi massal + panel AI kontekstual di kanan (diff, impact,
// terapkan selektif). Menggantikan pratinjau read-only untuk tahap ber-item.
import { Fragment, useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { ItemCandidate } from "@/lib/api";
import { regenerateItem, applyItem, pinItem } from "../actions";
import { getCpmk, getSubCpmk, getMinggu, getKomponen, type Draf } from "./draft";

const ACTIONS: { v: string; l: string }[] = [
  { v: "perbaiki_redaksi", l: "Perbaiki redaksi" },
  { v: "buat_alternatif", l: "Buat alternatif" },
  { v: "naikkan_taksonomi", l: "Naikkan taksonomi" },
  { v: "periksa_konsistensi", l: "Periksa konsistensi" },
  { v: "perkaya", l: "Perkaya nilai" },
];

const LABEL: Record<string, string> = {
  kode: "Kode", deskripsi: "Rumusan", cpl_kode: "CPL", cpmk_kode: "CPMK induk",
  sub_cpmk_kode: "Sub-CPMK", taksonomi_kode: "Taksonomi", indikator: "Indikator",
  kriteria_penilaian: "Kriteria & bentuk", metode_pembelajaran: "Metode",
  bentuk_luring: "Luring", bentuk_daring: "Daring", pengalaman_belajar: "Penugasan",
  materi_pustaka: "Materi/pustaka", bobot_penilaian: "Bobot", nama: "Nama",
  jenis: "Jenis", instrumen: "Instrumen", bobot_persen: "Bobot (%)", minggu_ke: "Minggu",
};

type Badge = { text: string; tone: "blue" | "amber" | "neutral" };
type Row = { id: string; code: string; statement: string; badges: Badge[]; source: string; pinned: boolean; needsReview: boolean };

function sourceLabel(src?: string): string {
  switch (src) {
    case "ai_edited": return "AI + disunting";
    case "ai": return "AI";
    case "manual": return "Manual";
    default: return "—";
  }
}

function rowsFor(stage: string, draf: Draf): Row[] {
  if (stage === "cpmk") {
    return getCpmk(draf).filter((c) => c._id).map((c) => ({
      id: c._id!, code: c.kode, statement: c.deskripsi,
      badges: [
        ...(c.cpl_kode ?? []).map((x): Badge => ({ text: x, tone: "blue" })),
        ...(c.taksonomi_kode ?? []).map((x): Badge => ({ text: x, tone: "amber" })),
      ],
      source: sourceLabel(), pinned: !!c._pin, needsReview: !!c._needs_review,
    }));
  }
  if (stage === "sub_cpmk") {
    return getSubCpmk(draf).filter((s) => s._id).map((s) => ({
      id: s._id!, code: s.kode, statement: s.deskripsi,
      badges: [
        ...(s.cpmk_kode ? [{ text: `← ${s.cpmk_kode}`, tone: "neutral" as const }] : []),
        ...(s.taksonomi_kode ?? []).map((x): Badge => ({ text: x, tone: "amber" })),
      ],
      source: sourceLabel(), pinned: !!s._pin, needsReview: !!s._needs_review,
    }));
  }
  if (stage === "mingguan") {
    return getMinggu(draf).filter((m) => m._id).map((m) => ({
      id: m._id!, code: `Mg ${m.minggu_ke}`,
      statement: m.materi_pustaka || m.indikator || m.sub_cpmk_kode || "—",
      badges: [
        ...(m.sub_cpmk_kode ? [{ text: m.sub_cpmk_kode, tone: "blue" as const }] : []),
        ...(m.bobot_penilaian != null ? [{ text: `${m.bobot_penilaian}%`, tone: "neutral" as const }] : []),
      ],
      source: sourceLabel(), pinned: !!m._pin, needsReview: !!m._needs_review,
    }));
  }
  return getKomponen(draf).filter((k) => k._id).map((k) => ({
    id: k._id!, code: k.nama,
    statement: [k.jenis, k.instrumen].filter(Boolean).join(" · ") || "—",
    badges: [
      ...(k.sub_cpmk_kode ? [{ text: k.sub_cpmk_kode, tone: "blue" as const }] : []),
      ...(k.bobot_persen != null ? [{ text: `${k.bobot_persen}%`, tone: "neutral" as const }] : []),
    ],
    source: sourceLabel(), pinned: !!k._pin, needsReview: !!k._needs_review,
  }));
}

function fmt(v: unknown): string {
  if (v == null) return "—";
  if (Array.isArray(v)) return v.join(", ");
  if (typeof v === "object") return JSON.stringify(v);
  return String(v);
}

function DiffView({ before, after }: { before: Record<string, unknown>; after: Record<string, unknown> }) {
  const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)])).filter((k) => !k.startsWith("_") && k !== "rubrik");
  const changed = keys.filter((k) => fmt(before[k]) !== fmt(after[k]));
  if (changed.length === 0) return <p className="text-xs text-muted">AI tidak mengusulkan perubahan berarti.</p>;
  return (
    <div className="space-y-2">
      {changed.map((k) => (
        <div key={k} className="overflow-hidden rounded-md border border-border text-xs">
          <div className="border-b border-border bg-gray-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted">{LABEL[k] ?? k}</div>
          <div className="bg-rose-50 px-2 py-1.5 text-rose-700 line-through">{fmt(before[k])}</div>
          <div className="border-t border-border bg-emerald-50 px-2 py-1.5 text-emerald-800">{fmt(after[k])}</div>
        </div>
      ))}
    </div>
  );
}

const badgeCls: Record<Badge["tone"], string> = {
  blue: "bg-blue-50 text-blue-700 ring-blue-200",
  amber: "bg-amber-50 text-amber-700 ring-amber-200",
  neutral: "bg-gray-50 text-gray-600 ring-gray-200",
};

export function GeneratorWorkspace({
  stage,
  draf,
  sessionId,
  revisi,
}: {
  stage: string;
  draf: Draf;
  sessionId: number;
  revisi: number;
}) {
  const rows = useMemo(() => rowsFor(stage, draf), [stage, draf]);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [active, setActive] = useState<string | null>(null);
  const [action, setAction] = useState("perbaiki_redaksi");
  const [instruction, setInstruction] = useState("");
  const [cand, setCand] = useState<ItemCandidate | null>(null);
  const [pending, start] = useTransition();
  const router = useRouter();
  const toast = useToast();

  const allChecked = rows.length > 0 && selected.size === rows.length;

  const toggle = (id: string) =>
    setSelected((s) => {
      const n = new Set(s);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
  const toggleAll = () => setSelected(allChecked ? new Set() : new Set(rows.map((r) => r.id)));

  const openAi = (id: string) => {
    setActive((cur) => (cur === id ? null : id));
    setCand(null);
  };

  const buatUsulan = () =>
    start(async () => {
      if (!active) return;
      setCand(null);
      const r = await regenerateItem(sessionId, stage, active, { action, instruction: instruction.trim() || undefined });
      if (!r.ok || !r.candidate) {
        toast({ type: "error", message: r.message ?? "Gagal membuat usulan." });
        return;
      }
      setCand(r.candidate);
    });

  const terapkan = () =>
    start(async () => {
      if (!cand || !active) return;
      const r = await applyItem(sessionId, stage, active, cand.after, cand.base_revisi);
      if (!r.ok) {
        toast({ type: r.status === 409 ? "warning" : "error", message: r.message ?? "Gagal menerapkan usulan." });
        return;
      }
      toast({ type: "success", message: "Usulan diterapkan. Versi sebelumnya tercatat." });
      setCand(null);
      router.refresh();
    });

  const setPin = (id: string, pinned: boolean) =>
    start(async () => {
      const r = await pinItem(sessionId, stage, id, pinned);
      if (!r.ok) {
        toast({ type: "error", message: r.message ?? "Gagal mengubah sematan." });
        return;
      }
      router.refresh();
    });

  const bulkPin = (pinned: boolean) =>
    start(async () => {
      for (const id of selected) {
        await pinItem(sessionId, stage, id, pinned);
      }
      setSelected(new Set());
      router.refresh();
    });

  return (
    <div className="rounded-xl border border-border bg-surface">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2">
          <label className="flex items-center gap-2 text-xs font-medium text-muted">
            <input type="checkbox" className="h-4 w-4 accent-brand-600" checked={allChecked} onChange={toggleAll} />
            {selected.size > 0 ? `${selected.size} dipilih` : "Pilih semua"}
          </label>
          <div className="flex flex-wrap gap-1.5">
            <button type="button" disabled={pending || selected.size === 0} onClick={() => bulkPin(true)} className={buttonClass("ghost", "sm")}>
              📌 Sematkan
            </button>
            <button type="button" disabled={pending || selected.size === 0} onClick={() => bulkPin(false)} className={buttonClass("ghost", "sm")}>
              Lepas sematan
            </button>
          </div>
        </div>
        <ul className="divide-y divide-border">
          {rows.length === 0 && <li className="px-3 py-6 text-center text-sm text-muted">Belum ada item.</li>}
          {rows.map((r) => {
            const isActive = r.id === active;
            return (
              <Fragment key={r.id}>
              <li
                className={`flex items-start gap-3 px-3 py-2.5 ${isActive ? "bg-brand-50/40" : selected.has(r.id) ? "bg-brand-50/20" : ""}`}
              >
                <input
                  type="checkbox"
                  className="mt-1 h-4 w-4 accent-brand-600"
                  checked={selected.has(r.id)}
                  onChange={() => toggle(r.id)}
                  aria-label={`Pilih ${r.code}`}
                />
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <span className="font-mono text-xs font-bold text-brand-700">{r.code}</span>
                    {r.badges.map((b, i) => (
                      <span key={i} className={`rounded-md px-1.5 py-0.5 text-[10px] font-medium ring-1 ${badgeCls[b.tone]}`}>{b.text}</span>
                    ))}
                    {r.pinned && <span className="text-[10px] text-brand-600">📌</span>}
                    {r.needsReview && (
                      <span className="rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-700">perlu tinjau</span>
                    )}
                  </div>
                  <p className="mt-1 text-sm text-ink">{r.statement}</p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                  <button
                    type="button"
                    disabled={pending}
                    onClick={() => setPin(r.id, !r.pinned)}
                    className={`grid h-7 w-7 place-items-center rounded-md text-xs ${r.pinned ? "bg-brand-100 text-brand-700" : "text-muted hover:bg-gray-100"}`}
                    title={r.pinned ? "Lepas sematan" : "Sematkan"}
                  >
                    📌
                  </button>
                  <button
                    type="button"
                    disabled={pending || r.pinned}
                    onClick={() => openAi(r.id)}
                    className={`grid h-7 w-7 place-items-center rounded-md text-xs ${isActive ? "bg-brand-100 text-brand-700" : "text-muted hover:bg-gray-100"}`}
                    title={r.pinned ? "Lepas sematan dulu" : "Perbaiki dengan AI"}
                  >
                    ✨
                  </button>
                </div>
              </li>
              {isActive && (
                <li className="bg-brand-50/10 px-3 pb-3">
                  <div className="rounded-lg border border-brand-200 bg-surface p-3">
                    <div className="flex flex-wrap gap-1.5">
                      {ACTIONS.map((a) => (
                        <button
                          key={a.v}
                          type="button"
                          onClick={() => setAction(a.v)}
                          className={`rounded-md border px-2 py-1 text-[11px] font-medium ${action === a.v ? "border-brand-400 bg-brand-100 text-brand-800" : "border-border bg-surface text-gray-600 hover:bg-gray-50"}`}
                        >
                          {a.l}
                        </button>
                      ))}
                    </div>
                    <textarea
                      value={instruction}
                      onChange={(e) => setInstruction(e.target.value)}
                      placeholder="Instruksi tambahan (opsional)…"
                      rows={2}
                      className="mt-2 w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs outline-none focus:border-brand-400"
                    />
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                      <button type="button" disabled={pending} onClick={buatUsulan} className={buttonClass("primary", "sm")}>
                        {pending && !cand ? "Menyusun…" : "Buat usulan"}
                      </button>
                      <button type="button" disabled={pending} onClick={() => { setActive(null); setCand(null); }} className={buttonClass("ghost", "sm")}>
                        Tutup
                      </button>
                      <span className="text-[10px] text-muted">Usulan tidak langsung mengubah draf.</span>
                    </div>
                    {cand && (
                      <div className="mt-3 border-t border-border pt-3">
                        <p className="mb-1.5 text-[11px] font-semibold text-ink">Pratinjau perubahan</p>
                        <DiffView before={cand.before} after={cand.after} />
                        {cand.base_revisi !== revisi && (
                          <p className="mt-2 rounded-md bg-amber-50 px-2 py-1 text-[11px] text-amber-700">Draf berubah sejak usulan dibuat. Buat usulan ulang.</p>
                        )}
                        <div className="mt-2 flex items-center gap-2">
                          <button type="button" disabled={pending || cand.base_revisi !== revisi} onClick={terapkan} className={buttonClass("primary", "sm")}>
                            Terapkan
                          </button>
                          <button type="button" disabled={pending} onClick={() => setCand(null)} className={buttonClass("ghost", "sm")}>
                            Tolak
                          </button>
                          {cand.usage?.estimated_usd != null && (
                            <span className="text-[10px] text-muted">{cand.usage.model ?? "AI"} · ~${cand.usage.estimated_usd}</span>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                </li>
              )}
              </Fragment>
            );
          })}
        </ul>
    </div>
  );
}
