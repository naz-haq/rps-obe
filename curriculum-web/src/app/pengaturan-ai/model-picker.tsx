"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Badge, buttonClass } from "@/components/ui";
import type { AiLiveModels, AiPengaturan } from "@/lib/api";
import { fetchLiveModels, setModelOverride } from "./actions";

const SUMBER_TONE: Record<string, "brand" | "ok" | "neutral"> = {
  override: "brand",
  profil: "ok",
  default: "neutral",
};
const SUMBER_LABEL: Record<string, string> = {
  override: "manual",
  profil: "profil",
  default: "default",
};

export function ModelPicker({ cfg }: { cfg: AiPengaturan }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const tasks = cfg.tasks ?? [];
  const efektif = cfg.model_efektif ?? {};
  const profilMap = cfg.profiles[cfg.profil_aktif] ?? {};

  // State pilihan per-tugas: "" = ikut profil (hapus override).
  const [pick, setPick] = useState<Record<string, string>>(() => ({ ...(cfg.model_override ?? {}) }));

  // Model LIVE per-provider (ditarik dari API key aktif) — dimuat saat mount.
  const [live, setLive] = useState<AiLiveModels>({});
  const [liveLoading, setLiveLoading] = useState(true);
  useEffect(() => {
    let aktif = true;
    fetchLiveModels()
      .then((data) => aktif && setLive(data))
      .finally(() => aktif && setLiveLoading(false));
    return () => {
      aktif = false;
    };
  }, []);

  // Provider dari sebuah nilai pilihan: mendukung key katalog & "provider::id".
  const providerOf = (key?: string) => {
    if (!key) return null;
    if (key.includes("::")) return key.split("::")[0];
    return cfg.models.find((m) => m.key === key)?.provider ?? null;
  };

  const liveProviders = useMemo(
    () => Object.keys(live).filter((p) => (live[p]?.length ?? 0) > 0),
    [live],
  );

  // Provider efektif per-tugas berdasarkan pilihan saat ini (override → profil → default).
  const providerEfektif = (task: string): string | null => {
    const chosen = pick[task];
    if (chosen) return providerOf(chosen);
    const prof = profilMap[task];
    if (prof) return providerOf(prof);
    return efektif[task]?.provider ?? null;
  };

  // Deteksi pelanggaran lintas-provider (validator ≠ provider generate, dst).
  const konflik = useMemo(() => {
    const out: Record<string, string> = {};
    for (const t of tasks) {
      if (t.cross_provider_of) {
        const p1 = providerEfektif(t.key);
        const p2 = providerEfektif(t.cross_provider_of);
        if (p1 && p1 === p2) {
          out[t.key] = `Harus beda provider dari '${t.cross_provider_of}' (kini sama-sama '${p1}').`;
        }
      }
    }
    return out;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pick, tasks]);

  const adaKonflik = Object.keys(konflik).length > 0;

  const modelTersedia = cfg.models.filter((m) => m.provider !== "mock" && m.tersedia);
  const modelTakTersedia = cfg.models.filter((m) => m.provider !== "mock" && !m.tersedia);

  // Pencarian & filter provider untuk daftar model yang panjang (live 100+).
  const [q, setQ] = useState("");
  const [providerFilter, setProviderFilter] = useState("");
  const qq = q.trim().toLowerCase();
  const cocok = (s: string) => !qq || s.toLowerCase().includes(qq);
  const allProviders = Array.from(new Set([...modelTersedia.map((m) => m.provider), ...liveProviders]));
  const catFiltered = modelTersedia.filter(
    (m) => (!providerFilter || m.provider === providerFilter) && cocok(`${m.key} ${m.provider} ${m.model}`),
  );
  const liveFiltered = liveProviders
    .filter((p) => !providerFilter || p === providerFilter)
    .map((p) => [p, live[p].filter((id) => cocok(`${p} ${id}`))] as const)
    .filter(([, ids]) => ids.length > 0);
  const totalCocok = catFiltered.length + liveFiltered.reduce((n, [, ids]) => n + ids.length, 0);

  const simpan = () => {
    setMsg(null);
    const bersih: Record<string, string> = {};
    Object.entries(pick).forEach(([k, v]) => {
      if (v) bersih[k] = v;
    });
    startTransition(async () => {
      const res = await setModelOverride(bersih);
      if (!res.ok) {
        setMsg({ ok: false, text: res.message ?? "Gagal menyimpan." });
        return;
      }
      setMsg({ ok: true, text: "Pemilihan model tersimpan." });
      router.refresh();
    });
  };

  const reset = () => {
    setPick({});
    setMsg(null);
  };

  return (
    <div className="space-y-4">
      {modelTersedia.length === 0 && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
          Belum ada provider dengan API key aktif. Isi key di <code>.env</code> (mis. <code>GEMINI_API_KEY</code>,
          <code> OPENAI_API_KEY</code>, <code>NVIDIA_API_KEY</code>) agar model bisa dipilih.
        </div>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <input
          type="search"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Cari model… (mis. gpt, llama, qwen, flash)"
          className="min-w-[220px] flex-1 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs outline-none focus:border-brand-400"
        />
        {allProviders.length > 1 && (
          <select
            value={providerFilter}
            onChange={(e) => setProviderFilter(e.target.value)}
            className="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs outline-none focus:border-brand-400"
          >
            <option value="">Semua provider</option>
            {allProviders.map((p) => (
              <option key={p} value={p}>
                {p}
              </option>
            ))}
          </select>
        )}
        <span className="text-[11px] text-muted">
          {liveLoading
            ? "memuat model live…"
            : liveProviders.length > 0
              ? `${totalCocok} model cocok · live: ${liveProviders.map((p) => `${p} ${live[p].length}`).join(", ")}`
              : `${totalCocok} model (katalog saja — live tak terbaca)`}
        </span>
      </div>

      <div className="overflow-x-auto rounded-xl border border-border">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-muted">
            <tr>
              <th className="px-4 py-2.5">Tugas</th>
              <th className="px-4 py-2.5">Model dipakai (efektif)</th>
              <th className="px-4 py-2.5">Pilih model</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {tasks.map((t) => {
              const eff = efektif[t.key];
              const konf = konflik[t.key];
              return (
                <tr key={t.key} className="align-top">
                  <td className="px-4 py-3">
                    <p className="font-medium text-ink">{t.label}</p>
                    <code className="text-[11px] text-muted">{t.key}</code>
                    {t.cross_provider_of && (
                      <p className="mt-0.5 text-[11px] text-amber-600">
                        wajib beda provider dari <code>{t.cross_provider_of}</code>
                      </p>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {eff ? (
                      <span className="flex flex-wrap items-center gap-1.5">
                        <code className="text-xs text-ink">{eff.model}</code>
                        <Badge tone={SUMBER_TONE[eff.sumber] ?? "neutral"}>
                          {SUMBER_LABEL[eff.sumber] ?? eff.sumber}
                        </Badge>
                        {eff.provider && <span className="text-[11px] text-muted">{eff.provider}</span>}
                      </span>
                    ) : (
                      <span className="text-xs text-muted">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <select
                      value={pick[t.key] ?? ""}
                      onChange={(e) => setPick((p) => ({ ...p, [t.key]: e.target.value }))}
                      className={`w-full rounded-lg border bg-surface px-2.5 py-1.5 text-xs outline-none focus:border-brand-400 ${
                        konf ? "border-rose-300" : "border-border"
                      }`}
                    >
                      <option value="">
                        Ikuti profil{profilMap[t.key] ? ` (${profilMap[t.key]})` : ""}
                      </option>
                      {/* Nilai live tersimpan tapi daftar live belum termuat: tetap tampilkan. */}
                      {pick[t.key]?.includes("::") &&
                        !liveProviders.some((p) =>
                          (live[p] ?? []).some((id) => `${p}::${id}` === pick[t.key]),
                        ) && (
                          <option value={pick[t.key]}>{pick[t.key].replace("::", " · ")}</option>
                        )}
                      {catFiltered.length > 0 && (
                        <optgroup label="Katalog (ada API key)">
                          {catFiltered.map((m) => (
                            <option key={m.key} value={m.key}>
                              {m.key} · {m.provider}
                            </option>
                          ))}
                        </optgroup>
                      )}
                      {liveFiltered.map(([p, ids]) => (
                        <optgroup key={p} label={`Live · ${p} (${ids.length})`}>
                          {ids.map((id) => (
                            <option key={`${p}::${id}`} value={`${p}::${id}`}>
                              {id} · {p}
                            </option>
                          ))}
                        </optgroup>
                      ))}
                      {modelTakTersedia.length > 0 && (
                        <optgroup label="Tanpa API key (tak bisa dipilih)">
                          {modelTakTersedia.map((m) => (
                            <option key={m.key} value={m.key} disabled>
                              {m.key} · {m.provider} (tanpa key)
                            </option>
                          ))}
                        </optgroup>
                      )}
                    </select>
                    {konf && <p className="mt-1 text-[11px] text-rose-600">{konf}</p>}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {msg && (
        <p className={`text-xs ${msg.ok ? "text-emerald-600" : "text-rose-600"}`}>{msg.text}</p>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <button
          type="button"
          disabled={pending || adaKonflik}
          onClick={simpan}
          className={buttonClass("primary", "sm")}
          title={adaKonflik ? "Perbaiki konflik lintas-provider dulu" : undefined}
        >
          {pending ? "Menyimpan…" : "Simpan pemilihan model"}
        </button>
        <button type="button" disabled={pending} onClick={reset} className={buttonClass("ghost", "sm")}>
          Kosongkan (ikut profil)
        </button>
        {adaKonflik && (
          <span className="text-[11px] text-rose-600">Ada konflik lintas-provider — perbaiki dulu.</span>
        )}
      </div>
    </div>
  );
}
