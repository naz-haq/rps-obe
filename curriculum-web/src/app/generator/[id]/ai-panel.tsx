"use client";

import { useRef, useState, useTransition } from "react";
import { Badge, buttonClass } from "@/components/ui";
import { runAudit, chatConsult, type AuditHasil, type ChatMessage } from "../actions";

const STATUS_TONE: Record<string, "ok" | "warn" | "danger" | "neutral"> = {
  "Sangat Selaras": "ok",
  "Selaras": "ok",
  "Cukup Selaras": "warn",
  "Kurang Selaras": "danger",
  "Tidak Selaras": "danger",
};

const ISU_TONE: Record<string, "ok" | "warn" | "danger" | "neutral"> = {
  success: "ok",
  info: "neutral",
  warning: "warn",
  error: "danger",
};

export function AiPanel({ sessionId }: { sessionId: number }) {
  const [tab, setTab] = useState<"chat" | "audit">("chat");

  return (
    <div className="flex h-full flex-col">
      <div className="flex gap-1 border-b border-border p-2">
        <TabButton active={tab === "chat"} onClick={() => setTab("chat")} label="Konsultan AI" />
        <TabButton active={tab === "audit"} onClick={() => setTab("audit")} label="Audit Keselarasan" />
      </div>
      <div className="min-h-0 flex-1">
        {tab === "chat" ? <ChatTab sessionId={sessionId} /> : <AuditTab sessionId={sessionId} />}
      </div>
    </div>
  );
}

function TabButton({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition ${
        active ? "bg-brand-50 text-brand-700" : "text-gray-600 hover:bg-gray-50"
      }`}
    >
      {label}
    </button>
  );
}

function ChatTab({ sessionId }: { sessionId: number }) {
  const [messages, setMessages] = useState<ChatMessage[]>([
    {
      sender: "ai",
      text: "Halo! Saya konsultan kurikulum OBE. Tanyakan apa saja tentang CPL, CPMK, taksonomi Bloom, metode pembelajaran, atau penilaian RPS ini.",
    },
  ]);
  const [input, setInput] = useState("");
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const endRef = useRef<HTMLDivElement>(null);

  const send = () => {
    const text = input.trim();
    if (!text || pending) return;
    const next: ChatMessage[] = [...messages, { sender: "user", text }];
    setMessages(next);
    setInput("");
    setError(null);
    startTransition(async () => {
      const res = await chatConsult(sessionId, next);
      if (!res.ok) {
        setError(res.message ?? "Gagal menghubungi AI.");
        return;
      }
      setMessages([...next, { sender: "ai", text: res.text ?? "" }]);
      requestAnimationFrame(() => endRef.current?.scrollIntoView({ behavior: "smooth" }));
    });
  };

  return (
    <div className="flex h-full flex-col">
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
        {messages.map((m, i) => (
          <div key={i} className={`flex ${m.sender === "user" ? "justify-end" : "justify-start"}`}>
            <div
              className={`max-w-[85%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-xs leading-relaxed ${
                m.sender === "user"
                  ? "bg-brand-600 text-white"
                  : "border border-border bg-surface text-ink"
              }`}
            >
              {m.text}
            </div>
          </div>
        ))}
        {pending && (
          <div className="flex justify-start">
            <div className="rounded-2xl border border-border bg-surface px-3 py-2 text-xs text-muted">
              Mengetik…
            </div>
          </div>
        )}
        <div ref={endRef} />
      </div>
      {error && <p className="px-4 text-xs text-rose-600">{error}</p>}
      <div className="border-t border-border p-3">
        <div className="flex items-end gap-2">
          <textarea
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                send();
              }
            }}
            rows={2}
            placeholder="Tulis pertanyaan… (Enter untuk kirim)"
            className="min-h-[42px] flex-1 resize-none rounded-lg border border-border bg-surface px-3 py-2 text-xs outline-none focus:border-brand-400"
          />
          <button
            type="button"
            onClick={send}
            disabled={pending || !input.trim()}
            className={buttonClass("primary", "sm")}
          >
            Kirim
          </button>
        </div>
      </div>
    </div>
  );
}

function AuditTab({ sessionId }: { sessionId: number }) {
  const [hasil, setHasil] = useState<AuditHasil | null>(null);
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);

  const jalankan = () => {
    setError(null);
    startTransition(async () => {
      const res = await runAudit(sessionId);
      if (!res.ok || !res.data) {
        setError(res.message ?? "Audit gagal.");
        return;
      }
      setHasil(res.data);
    });
  };

  return (
    <div className="flex h-full flex-col">
      <div className="border-b border-border p-3">
        <button
          type="button"
          onClick={jalankan}
          disabled={pending}
          className={`w-full ${buttonClass("primary", "sm")}`}
        >
          {pending ? "Menganalisis keselarasan…" : "Jalankan Audit AI"}
        </button>
        <p className="mt-2 text-[11px] text-muted">
          AI menilai keselarasan konstruktif (CPL→CPMK→Sub-CPMK→penilaian) draf saat ini.
        </p>
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto p-4">
        {error && <p className="text-xs text-rose-600">{error}</p>}
        {!hasil && !error && (
          <p className="text-xs italic text-muted">Belum ada hasil audit. Klik tombol di atas.</p>
        )}
        {hasil && (
          <div className="space-y-4">
            <div className="flex items-center gap-3 rounded-xl border border-border bg-surface p-3">
              <div className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-brand-50 text-lg font-bold text-brand-700">
                {hasil.skor_keseluruhan}
              </div>
              <div>
                <Badge tone={STATUS_TONE[hasil.status] ?? "neutral"}>{hasil.status}</Badge>
                <p className="mt-1 text-[11px] leading-relaxed text-gray-600">{hasil.umpan_balik}</p>
              </div>
            </div>
            <div className="space-y-2">
              <p className="text-xs font-semibold text-gray-500">Temuan ({hasil.isu.length})</p>
              {hasil.isu.map((isu, i) => (
                <div key={i} className="rounded-lg border border-border bg-surface p-2.5">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <Badge tone={ISU_TONE[isu.tipe] ?? "neutral"}>{isu.tipe}</Badge>
                    <span className="text-[10px] font-semibold uppercase text-gray-400">{isu.kategori}</span>
                    {isu.kode_target && <span className="text-[10px] font-mono text-gray-500">{isu.kode_target}</span>}
                  </div>
                  <p className="mt-1 text-[11px] leading-relaxed text-ink">{isu.pesan}</p>
                  {isu.saran && (
                    <p className="mt-1 text-[11px] leading-relaxed text-emerald-700">
                      <span className="font-semibold">Saran:</span> {isu.saran}
                    </p>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
