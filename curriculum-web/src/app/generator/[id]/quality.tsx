// Ringkasan mutu draf (Fase 2 — audit §4.6 & prototipe §8): metrik konkret dari
// isi draf, bukan angka hiasan. Dipakai QualityStrip + hitungan per-tahap.
import { getCpmk, getSubCpmk, getMinggu, getKomponen, type Draf, type ItemMeta } from "./draft";

type AnyItem = ItemMeta & Record<string, unknown>;

export function stageItems(draf: Draf, stage: string): AnyItem[] {
  switch (stage) {
    case "cpmk":
      return getCpmk(draf) as AnyItem[];
    case "sub_cpmk":
      return getSubCpmk(draf) as AnyItem[];
    case "mingguan":
      return getMinggu(draf) as AnyItem[];
    case "penilaian":
      return getKomponen(draf) as AnyItem[];
    default:
      return [];
  }
}

/** Teks ringkas per tahap: "N butir · x disematkan · y perlu tinjau". */
export function stageSummary(draf: Draf, stage: string, fallback: string): string {
  const items = stageItems(draf, stage);
  if (items.length === 0) return fallback;
  const pin = items.filter((i) => i._pin).length;
  const rev = items.filter((i) => i._needs_review).length;
  const parts = [`${items.length} butir`];
  if (pin) parts.push(`${pin} disematkan`);
  if (rev) parts.push(`${rev} perlu tinjau`);
  return parts.join(" · ");
}

/** Jumlah item per tahap (untuk badge stepper). */
export function stageCount(draf: Draf, stage: string): number {
  return stageItems(draf, stage).length;
}

/** Jumlah item "perlu ditinjau" per tahap. */
export function stageReviewCount(draf: Draf, stage: string): number {
  return stageItems(draf, stage).filter((i) => i._needs_review).length;
}

function allItems(draf: Draf): AnyItem[] {
  return [
    ...(getCpmk(draf) as AnyItem[]),
    ...(getSubCpmk(draf) as AnyItem[]),
    ...(getMinggu(draf) as AnyItem[]),
    ...(getKomponen(draf) as AnyItem[]),
  ];
}

type Tone = "good" | "warn" | "muted";

/** Deret metrik mutu ringkas di atas tab tahap. */
export function QualityStrip({ draf, catatan = {} }: { draf: Draf; catatan?: Record<string, unknown> }) {
  const cpmk = getCpmk(draf);
  const sub = getSubCpmk(draf);
  const minggu = getMinggu(draf);
  const komponen = getKomponen(draf);

  const terisi = [cpmk.length, sub.length, minggu.length, komponen.length].filter((n) => n > 0).length;
  const kelengkapan = Math.round((terisi / 4) * 100);
  // Keterlacakan gabungan: CPMK ber-CPL + Sub-CPMK ber-CPMK.
  const lacakOk = cpmk.filter((c) => (c.cpl_kode ?? []).length > 0).length + sub.filter((s) => !!s.cpmk_kode).length;
  const lacakTotal = cpmk.length + sub.length;
  const semua = allItems(draf);
  const perluTinjau = semua.filter((i) => i._needs_review).length;
  const tersemat = semua.filter((i) => i._pin).length;

  // Grounding dari catatan_validasi tiap tahap (klaim terverifikasi).
  let gKlaim = 0;
  let gDitolak = 0;
  let gDilewati = false;
  for (const v of Object.values(catatan)) {
    const c = v as { dilewati?: string | null; jumlah_klaim?: number; jumlah_ditolak?: number };
    if (c.dilewati) gDilewati = true;
    gKlaim += c.jumlah_klaim ?? 0;
    gDitolak += c.jumlah_ditolak ?? 0;
  }
  const groundingVal = gKlaim > 0 ? `${gKlaim - gDitolak}/${gKlaim}` : gDilewati ? "dilewati" : "—";
  const groundingTone: Tone = gKlaim > 0 ? (gDitolak === 0 ? "good" : "warn") : gDilewati ? "warn" : "muted";

  const metrics: { label: string; value: string; tone: Tone }[] = [
    { label: "Kelengkapan", value: `${kelengkapan}%`, tone: kelengkapan >= 100 ? "good" : kelengkapan >= 50 ? "warn" : "muted" },
    { label: "Keterlacakan", value: lacakTotal > 0 ? `${lacakOk}/${lacakTotal}` : "—", tone: lacakTotal > 0 && lacakOk === lacakTotal ? "good" : "warn" },
    { label: "Grounding", value: groundingVal, tone: groundingTone },
    { label: "Perlu ditinjau", value: String(perluTinjau), tone: perluTinjau > 0 ? "warn" : "good" },
    { label: "Tersemat", value: String(tersemat), tone: "muted" },
  ];

  return (
    <div className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-5">
      {metrics.map((m) => (
        <div key={m.label} className="bg-surface px-4 py-3">
          <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">{m.label}</p>
          <p
            className={`mt-1 text-lg font-semibold tabular-nums ${
              m.tone === "good" ? "text-emerald-600" : m.tone === "warn" ? "text-amber-600" : "text-ink"
            }`}
          >
            {m.value}
          </p>
        </div>
      ))}
    </div>
  );
}
