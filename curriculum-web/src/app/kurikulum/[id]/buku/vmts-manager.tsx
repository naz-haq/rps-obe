"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import type { ProdiVmts } from "@/lib/api";
import { saveVmts, selectVmts, removeVmts } from "./vmts-actions";

const toText = (arr: string[]) => (arr ?? []).join("\n");
const toList = (s: string) =>
  s
    .split("\n")
    .map((x) => x.trim())
    .filter(Boolean);

export function VmtsManager({
  kurikulumId,
  institusiId,
  currentVmtsId,
  versions,
}: {
  kurikulumId: string;
  institusiId: number;
  currentVmtsId: number | null;
  versions: ProdiVmts[];
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [editing, setEditing] = useState<ProdiVmts | "new" | null>(null);
  const [msg, setMsg] = useState<string | null>(null);

  const run = (fn: () => Promise<{ ok: boolean; message?: string }>, ok?: string) => {
    setMsg(null);
    startTransition(async () => {
      const res = await fn();
      if (res.ok) {
        setEditing(null);
        if (ok) setMsg(ok);
        router.refresh();
      } else {
        setMsg(res.message ?? "Gagal menyimpan.");
      }
    });
  };

  return (
    <div className="mb-4 rounded-lg border border-border bg-gray-50/60 p-3">
      <div className="flex flex-wrap items-center gap-2">
        <label className="text-xs font-medium text-ink">Versi VMTS dipakai:</label>
        <select
          className="rounded-md border border-border bg-surface px-2 py-1 text-sm"
          value={currentVmtsId ?? ""}
          disabled={pending}
          onChange={(e) => run(() => selectVmts(kurikulumId, e.target.value ? Number(e.target.value) : null))}
        >
          <option value="">— belum dipilih —</option>
          {versions.map((v) => (
            <option key={v.id} value={v.id}>
              {v.label}
            </option>
          ))}
        </select>
        <button
          type="button"
          disabled={pending}
          onClick={() => setEditing("new")}
          className="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-60"
        >
          + Versi baru
        </button>
        {currentVmtsId && (
          <button
            type="button"
            disabled={pending}
            onClick={() => setEditing(versions.find((v) => v.id === currentVmtsId) ?? null)}
            className="rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-medium text-ink hover:bg-gray-50 disabled:opacity-60"
          >
            Edit versi ini
          </button>
        )}
        {currentVmtsId && (
          <button
            type="button"
            disabled={pending}
            onClick={() => {
              if (confirm("Hapus versi VMTS ini?")) run(() => removeVmts(kurikulumId, currentVmtsId));
            }}
            className="rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-60"
          >
            Hapus versi
          </button>
        )}
        {pending && <span className="text-xs text-muted">Menyimpan…</span>}
      </div>
      {msg && <p className="mt-2 text-xs text-red-700">{msg}</p>}

      {editing && (
        <VmtsForm
          kurikulumId={kurikulumId}
          institusiId={institusiId}
          item={editing === "new" ? null : editing}
          pending={pending}
          onCancel={() => setEditing(null)}
          onSave={(payload) => run(() => saveVmts(kurikulumId, payload), "VMTS tersimpan.")}
        />
      )}
    </div>
  );
}

function VmtsForm({
  institusiId,
  item,
  pending,
  onCancel,
  onSave,
}: {
  kurikulumId: string;
  institusiId: number;
  item: ProdiVmts | null;
  pending: boolean;
  onCancel: () => void;
  onSave: (payload: {
    id?: number;
    institusi_id: number;
    label: string;
    visi: string | null;
    misi: string[];
    tujuan: string[];
    strategi: string[];
  }) => void;
}) {
  const [label, setLabel] = useState(item?.label ?? "");
  const [visi, setVisi] = useState(item?.visi ?? "");
  const [misi, setMisi] = useState(toText(item?.misi ?? []));
  const [tujuan, setTujuan] = useState(toText(item?.tujuan ?? []));
  const [strategi, setStrategi] = useState(toText(item?.strategi ?? []));

  return (
    <div className="mt-3 space-y-2 rounded-md border border-border bg-surface p-3">
      <div>
        <label className="text-xs font-medium text-ink">Label versi</label>
        <input
          value={label}
          onChange={(e) => setLabel(e.target.value)}
          placeholder="mis. VMTS 2024 / Renstra 2024-2029"
          className="mt-1 w-full rounded-md border border-border px-2 py-1 text-sm"
        />
      </div>
      <div>
        <label className="text-xs font-medium text-ink">Visi</label>
        <textarea
          value={visi}
          onChange={(e) => setVisi(e.target.value)}
          rows={2}
          className="mt-1 w-full rounded-md border border-border px-2 py-1 text-sm"
        />
      </div>
      {[
        { l: "Misi (satu poin per baris)", v: misi, s: setMisi },
        { l: "Tujuan (satu poin per baris)", v: tujuan, s: setTujuan },
        { l: "Strategi (satu poin per baris)", v: strategi, s: setStrategi },
      ].map((f) => (
        <div key={f.l}>
          <label className="text-xs font-medium text-ink">{f.l}</label>
          <textarea
            value={f.v}
            onChange={(e) => f.s(e.target.value)}
            rows={3}
            placeholder="Poin 1&#10;Poin 2&#10;Poin 3"
            className="mt-1 w-full rounded-md border border-border px-2 py-1 text-sm"
          />
        </div>
      ))}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={onCancel} className="rounded-md border border-border px-2.5 py-1 text-xs">
          Batal
        </button>
        <button
          type="button"
          disabled={pending || !label.trim()}
          onClick={() =>
            onSave({
              id: item?.id,
              institusi_id: institusiId,
              label: label.trim() || "VMTS",
              visi: visi.trim() || null,
              misi: toList(misi),
              tujuan: toList(tujuan),
              strategi: toList(strategi),
            })
          }
          className="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-60"
        >
          Simpan versi
        </button>
      </div>
    </div>
  );
}
