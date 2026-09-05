"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Badge, buttonClass } from "@/components/ui";
import { SearchableSelect } from "@/components/modal";
import type { AiPengaturan } from "@/lib/api";
import { setEmbeddingModel } from "./actions";

export function EmbeddingPicker({ cfg }: { cfg: AiPengaturan }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const currentKey = `${cfg.embedding.provider}::${cfg.embedding.model}`;
  const [selected, setSelected] = useState(currentKey);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const selectedModel = cfg.embedding_models.find((model) => model.key === selected);
  const dimensionsChanged = selectedModel && selectedModel.dimensions !== cfg.embedding.dimensions;

  const save = () => {
    setMessage(null);
    startTransition(async () => {
      const result = await setEmbeddingModel(selected);
      if (!result.ok) {
        setMessage({ ok: false, text: result.message ?? "Gagal menyimpan model embedding." });
        return;
      }
      setMessage({ ok: true, text: "Model embedding tersimpan." });
      router.refresh();
    });
  };

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-end gap-3">
        <label className="min-w-[280px] flex-1 text-xs font-medium text-ink">
          Model embedding
          <SearchableSelect
            className="mt-1.5"
            value={selected}
            allowClear={false}
            onChange={(v) => {
              setSelected(v);
              setMessage(null);
            }}
            options={cfg.embedding_models.map((model) => ({
              value: model.key,
              label: `${model.model} · ${model.provider} · ${model.dimensions.toLocaleString("id-ID")} dimensi${!model.tersedia ? " (tanpa API key)" : ""}`,
              disabled: !model.tersedia,
            }))}
          />
        </label>
        <button
          type="button"
          onClick={save}
          disabled={pending || !selectedModel?.tersedia || selected === currentKey}
          className={buttonClass("primary", "sm")}
        >
          {pending ? "Menyimpan…" : "Simpan embedding"}
        </button>
      </div>

      <div className="flex flex-wrap items-center gap-2 text-xs text-muted">
        <span>Aktif:</span>
        <Badge tone="ok">{cfg.embedding.provider}</Badge>
        <code>{cfg.embedding.model}</code>
        <span>· {cfg.embedding.dimensions.toLocaleString("id-ID")} dimensi</span>
      </div>

      {dimensionsChanged && (
        <p className="text-xs text-amber-700">
          Dimensi berubah. Setelah menyimpan, indeks ulang seluruh dokumen rujukan agar pencarian grounding tetap valid.
        </p>
      )}
      {message && (
        <p className={`text-xs ${message.ok ? "text-emerald-600" : "text-rose-600"}`}>{message.text}</p>
      )}
    </div>
  );
}