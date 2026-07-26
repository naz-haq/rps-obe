"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Modal } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { ProdiVmts } from "@/lib/api";
import { saveProdiVmts, deleteProdiVmts } from "./vmts-actions";

const toText = (arr: string[]) => (arr ?? []).join("\n");
const toList = (s: string) =>
  s
    .split("\n")
    .map((x) => x.trim())
    .filter(Boolean);

export function ManageVmtsButton({ institusiId, versions }: { institusiId: number; versions: ProdiVmts[] }) {
  return (
    <Modal trigger={`VMTS (${versions.length})`} title="Kelola VMTS Program Studi" triggerVariant="ghost" triggerSize="sm">
      {() => <VmtsPanel institusiId={institusiId} versions={versions} />}
    </Modal>
  );
}

function VmtsPanel({ institusiId, versions }: { institusiId: number; versions: ProdiVmts[] }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [editing, setEditing] = useState<ProdiVmts | "new" | null>(versions.length === 0 ? "new" : null);
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
    <div className="space-y-3">
      <p className="text-xs text-muted">
        Kelola beberapa versi VMTS. Saat menyusun Buku Kurikulum, tinggal pilih versi yang sesuai — tanpa input ulang.
      </p>

      {versions.length > 0 && (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {versions.map((v) => (
            <li key={v.id} className="flex items-start justify-between gap-2 px-3 py-2">
              <div className="min-w-0">
                <p className="text-sm font-medium text-ink">{v.label}</p>
                {v.visi && <p className="truncate text-xs text-muted">{v.visi}</p>}
                <p className="text-xs text-muted">
                  {v.misi.length} misi · {v.tujuan.length} tujuan · {v.strategi.length} strategi
                </p>
              </div>
              <div className="flex shrink-0 gap-1">
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => setEditing(v)}
                  className="rounded-md border border-border bg-surface px-2 py-1 text-xs text-ink hover:bg-gray-50"
                >
                  Edit
                </button>
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => {
                    if (confirm(`Hapus versi "${v.label}"?`)) run(() => deleteProdiVmts(v.id));
                  }}
                  className="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-700 hover:bg-red-100"
                >
                  Hapus
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {!editing && (
        <button type="button" onClick={() => setEditing("new")} className={buttonClass("secondary")}>
          + Versi VMTS baru
        </button>
      )}
      {msg && <p className="text-xs text-emerald-700">{msg}</p>}

      {editing && (
        <VmtsForm
          institusiId={institusiId}
          item={editing === "new" ? null : editing}
          pending={pending}
          onCancel={() => setEditing(null)}
          onSave={(payload) => run(() => saveProdiVmts(payload), "VMTS tersimpan.")}
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
    <div className="space-y-2 rounded-md border border-border bg-gray-50/60 p-3">
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
        <button type="button" onClick={onCancel} className={buttonClass("secondary")}>
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
          className={buttonClass("primary")}
        >
          Simpan versi
        </button>
      </div>
    </div>
  );
}
