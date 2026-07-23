"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Badge, PageHeader, buttonClass } from "@/components/ui";
import type { Cpl, GenerateSession, Taksonomi } from "@/lib/api";
import {
  generateStage,
  acceptStage,
  rejectStage,
  pinStage,
  commitSession,
} from "../actions";
import {
  type Draf,
  type CpmkItem,
  type SubCpmkItem,
  type MingguItem,
  type KomponenItem,
  getCpmk,
  getSubCpmk,
  getMinggu,
  getKomponen,
  validateStage,
} from "./draft";
import { CpmkEditor, SubCpmkEditor, MingguEditor, KomponenEditor } from "./stage-editors";
import { SelfCheck } from "./self-check";
import { CplCpmkMatrix } from "./matrix";
import { FloatingAiChat } from "./floating-ai";

const STAGES = [
  { key: "cpmk", label: "CPMK", desc: "Capaian Pembelajaran Mata Kuliah" },
  { key: "sub_cpmk", label: "Sub-CPMK", desc: "Sub-CPMK + indikator & taksonomi" },
  { key: "mingguan", label: "Rencana Mingguan", desc: "Rencana pekanan (jumlah pekan mengikuti pola MK)" },
  { key: "penilaian", label: "Penilaian", desc: "Komponen penilaian + bobot" },
] as const;

const LOCKED = ["accepted", "edited", "pinned"];
const STATUS_TONE: Record<string, "ok" | "neutral" | "warn" | "brand" | "danger"> = {
  accepted: "ok",
  edited: "ok",
  pinned: "brand",
  generated: "warn",
  rejected: "danger",
  perlu_review: "danger",
};

export function Builder({
  session,
  cplList,
  taksonomiList,
  estimasiWaktu = "",
}: {
  session: GenerateSession;
  cplList: Cpl[];
  taksonomiList: Taksonomi[];
  estimasiWaktu?: string;
}) {
  const router = useRouter();
  const [tab, setTab] = useState<string>("cpmk");
  const [pending, startTransition] = useTransition();
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const draf = (session.draf ?? {}) as Draf;
  const bagian = (session.status_bagian ?? {}) as Record<string, string>;
  const isLocked = (s: string) => LOCKED.includes(bagian[s] ?? "");
  const allLocked = STAGES.every((s) => isLocked(s.key));
  const committed = session.status === "committed" || !!session.rps_version_id;

  const act = (
    tag: string | null,
    fn: () => Promise<{ ok: boolean; message?: string }>,
  ) => {
    setError(null);
    setBusy(tag);
    startTransition(async () => {
      const res = await fn();
      setBusy(null);
      if (!res.ok) {
        setError(res.message ?? "Aksi gagal.");
        return;
      }
      router.refresh();
    });
  };

  const activeIndex = STAGES.findIndex((s) => s.key === tab);
  const activeStage = activeIndex >= 0 ? STAGES[activeIndex] : null;

  return (
    <div className="space-y-6">
      <PageHeader
        title={session.kode_mk ?? `Sesi #${session.id}`}
        subtitle={session.nama_mk ?? "Penyusunan RPS OBE bertahap"}
        actions={
          <>
            <Badge tone={committed ? "ok" : "warn"}>{session.status}</Badge>
            {committed ? (
              <a href={`/rps/${session.rps_version_id}`} className={buttonClass("secondary", "sm")}>
                Lihat Dokumen RPS →
              </a>
            ) : (
              <button
                type="button"
                disabled={!allLocked || pending}
                onClick={() => act(null, () => commitSession(session.id))}
                className={buttonClass("primary", "sm")}
                title={allLocked ? "Commit ke RPS resmi" : "Kunci keempat tahap dulu"}
              >
                {busy === null && pending ? "Mengomit…" : "Commit RPS"}
              </button>
            )}
            <Link href="/generator" className="text-sm text-brand-700 hover:underline">
              ← Semua sesi
            </Link>
          </>
        }
      />

      {error && (
        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700">{error}</div>
      )}

      {/* Tab per tahap + tab akhir Matriks & Diagnostik */}
      <div className="flex flex-wrap gap-1 rounded-xl border border-border bg-surface p-1">
        {STAGES.map((s, idx) => {
          const st = bagian[s.key] ?? "";
          const locked = isLocked(s.key);
          const generated = !!(draf as Record<string, unknown>)[s.key];
          const active = tab === s.key;
          return (
            <button
              key={s.key}
              type="button"
              onClick={() => setTab(s.key)}
              className={`inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                active ? "bg-brand-50 text-brand-700" : "text-gray-600 hover:bg-gray-50"
              }`}
            >
              <span
                className={`grid h-5 w-5 place-items-center rounded-full text-[10px] font-bold ${
                  locked
                    ? "bg-emerald-100 text-emerald-700"
                    : generated
                      ? "bg-amber-100 text-amber-700"
                      : "bg-gray-100 text-gray-500"
                }`}
              >
                {locked ? "✓" : idx + 1}
              </span>
              {s.label}
              {st && <Badge tone={STATUS_TONE[st] ?? "neutral"}>{st}</Badge>}
            </button>
          );
        })}
        <button
          type="button"
          onClick={() => setTab("matriks")}
          className={`inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
            tab === "matriks" ? "bg-brand-50 text-brand-700" : "text-gray-600 hover:bg-gray-50"
          }`}
        >
          📊 Matriks &amp; Diagnostik
        </button>
      </div>

      {activeStage ? (
        <StageCard
          key={activeStage.key}
          index={activeIndex}
          stage={activeStage}
          draf={draf}
          cplList={cplList}
          taksonomiList={taksonomiList}
          estimasiWaktu={estimasiWaktu}
          status={bagian[activeStage.key] ?? ""}
          locked={isLocked(activeStage.key)}
          prevLocked={activeIndex === 0 || isLocked(STAGES[activeIndex - 1].key)}
          committed={committed}
          pending={pending}
          busy={busy}
          onGenerate={() => act(activeStage.key, () => generateStage(session.id, activeStage.key))}
          onAccept={(edited) => act(activeStage.key, () => acceptStage(session.id, activeStage.key, edited))}
          onReject={() => act(activeStage.key, () => rejectStage(session.id, activeStage.key))}
          onPin={() => act(activeStage.key, () => pinStage(session.id, activeStage.key))}
        />
      ) : (
        <div className="space-y-6">
          <section className="rounded-xl border border-border bg-surface p-5">
            <h3 className="mb-1 text-sm font-semibold">Dashboard Diagnosis Mandiri (Self-Check)</h3>
            <p className="mb-4 text-xs text-muted">
              Analisis otomatis struktur RPS berdasarkan aturan OBE & taksonomi sebelum dinilai AI.
            </p>
            <SelfCheck draf={draf} cplList={cplList} />
          </section>
          <CplCpmkMatrix draf={draf} cplList={cplList} />
        </div>
      )}

      <FloatingAiChat sessionId={session.id} />
    </div>
  );
}

type StageDef = { key: string; label: string; desc: string };

function StageCard({
  index,
  stage,
  draf,
  cplList,
  taksonomiList,
  estimasiWaktu,
  status,
  locked,
  prevLocked,
  committed,
  pending,
  busy,
  onGenerate,
  onAccept,
  onReject,
  onPin,
}: {
  index: number;
  stage: StageDef;
  draf: Draf;
  cplList: Cpl[];
  taksonomiList: Taksonomi[];
  estimasiWaktu: string;
  status: string;
  locked: boolean;
  prevLocked: boolean;
  committed: boolean;
  pending: boolean;
  busy: string | null;
  onGenerate: () => void;
  onAccept: (edited: unknown) => void;
  onReject: () => void;
  onPin: () => void;
}) {
  const generated = !!(draf as Record<string, unknown>)[stage.key];

  return (
    <div
      className={`rounded-xl border bg-surface ${locked ? "border-emerald-200" : "border-border"}`}
    >
      <div className="flex items-center justify-between border-b border-border px-5 py-3">
        <div className="flex items-center gap-3">
          <span
            className={`grid h-7 w-7 place-items-center rounded-full text-xs font-semibold ${
              locked
                ? "bg-emerald-100 text-emerald-700"
                : generated
                  ? "bg-amber-100 text-amber-700"
                  : "bg-gray-100 text-gray-500"
            }`}
          >
            {index + 1}
          </span>
          <div>
            <h3 className="text-sm font-semibold">{stage.label}</h3>
            <p className="text-xs text-muted">{stage.desc}</p>
          </div>
        </div>
        {status && <Badge tone={STATUS_TONE[status] ?? "neutral"}>{status}</Badge>}
      </div>

      <div className="space-y-4 p-5">
        {!prevLocked ? (
          <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p className="flex items-center gap-1.5 font-semibold">
              <span aria-hidden>🔒</span> Tahap terkunci
            </p>
            <p className="mt-1 text-xs text-amber-700">
              Selesaikan &amp; kunci tahap{" "}
              <b>{index > 0 ? STAGES[index - 1].label : "sebelumnya"}</b> dengan tombol{" "}
              <b>Simpan &amp; Setujui</b> terlebih dahulu sebelum mengisi tahap ini.
            </p>
          </div>
        ) : (
          <>
            {!generated && !committed && !locked && (
              <div className="rounded-lg border border-dashed border-brand-200 bg-brand-50/40 px-3 py-2 text-xs text-gray-600">
                Isi <b>manual</b> pada kolom di bawah, atau klik <b>Generate AI</b> untuk mengisi
                otomatis lalu sunting sesuai kebutuhan.
              </div>
            )}
            <StageBody
              stage={stage.key}
              draf={draf}
              cplList={cplList}
              taksonomiList={taksonomiList}
              estimasiWaktu={estimasiWaktu}
              locked={locked}
              committed={committed}
              pending={pending}
              busy={busy}
              onGenerate={onGenerate}
              onAccept={onAccept}
              onReject={onReject}
              onPin={onPin}
              status={status}
              key={`${stage.key}:${status}:${JSON.stringify((draf as Record<string, unknown>)[stage.key])}`}
            />
          </>
        )}
      </div>
    </div>
  );
}

function StageBody({
  stage,
  draf,
  cplList,
  taksonomiList,
  estimasiWaktu,
  locked,
  committed,
  pending,
  busy,
  onGenerate,
  onAccept,
  onReject,
  onPin,
  status,
}: {
  stage: string;
  draf: Draf;
  cplList: Cpl[];
  taksonomiList: Taksonomi[];
  estimasiWaktu: string;
  locked: boolean;
  committed: boolean;
  pending: boolean;
  busy: string | null;
  onGenerate: () => void;
  onAccept: (edited: unknown) => void;
  onReject: () => void;
  onPin: () => void;
  status: string;
}) {
  // State editable diinisialisasi dari draf; key remount saat draf berubah via generate.
  const [cpmk, setCpmk] = useState<CpmkItem[]>(() => getCpmk(draf));
  const [sub, setSub] = useState<SubCpmkItem[]>(() => getSubCpmk(draf));
  const [minggu, setMinggu] = useState<MingguItem[]>(() => getMinggu(draf));
  const [komponen, setKomponen] = useState<KomponenItem[]>(() => getKomponen(draf));
  // Tahap terkunci tetap bisa disunting ulang selama sesi BELUM di-commit.
  const [editing, setEditing] = useState(false);
  const editable = !locked || editing;

  const generated = !!(draf as Record<string, unknown>)[stage];

  const issues = editable ? validateStage(stage, { cpmk, sub, minggu, komponen }) : [];
  const valid = issues.length === 0;

  const cpmkMap = new Map(getCpmk(draf).map((c) => [c.kode, c.deskripsi]));
  const subCpmkOptions = getSubCpmk(draf).map((s) => ({
    kode: s.kode,
    deskripsi: s.deskripsi,
    cpmk_kode: s.cpmk_kode,
    cpmk_deskripsi: s.cpmk_kode ? cpmkMap.get(s.cpmk_kode) ?? undefined : undefined,
  }));

  const editedPayload = () => {
    switch (stage) {
      case "cpmk":
        return { cpmk };
      case "sub_cpmk":
        return { sub_cpmk: sub };
      case "mingguan":
        return { minggu };
      case "penilaian":
        return { komponen };
      default:
        return {};
    }
  };

  return (
    <div className="space-y-4">
      {editable ? (
        <>
          {stage === "cpmk" && (
            <CpmkEditor value={cpmk} onChange={setCpmk} cplList={cplList} taksonomiList={taksonomiList} />
          )}
          {stage === "sub_cpmk" && (
            <SubCpmkEditor
              value={sub}
              onChange={setSub}
              cpmkList={getCpmk(draf).map((c) => c.kode)}
              taksonomiList={taksonomiList}
            />
          )}
          {stage === "mingguan" && (
            <MingguEditor
              value={minggu}
              onChange={setMinggu}
              subCpmkList={subCpmkOptions}
              estimasiWaktu={estimasiWaktu}
            />
          )}
          {stage === "penilaian" && (
            <KomponenEditor
              value={komponen}
              onChange={setKomponen}
              subCpmkList={subCpmkOptions}
              mingguList={getMinggu(draf).map((m) => m.minggu_ke)}
            />
          )}
        </>
      ) : (
        <LockedView stage={stage} draf={draf} estimasiWaktu={estimasiWaktu} />
      )}

      {editable && !committed && issues.length > 0 && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          <p className="mb-1 flex items-center gap-1.5 font-semibold">
            <span aria-hidden>⚠️</span> Belum bisa disimpan — lengkapi dulu:
          </p>
          <ul className="list-inside list-disc space-y-0.5 text-xs text-amber-700">
            {issues.map((it, i) => (
              <li key={i}>{it}</li>
            ))}
          </ul>
        </div>
      )}

      {!committed && (
        <div className="flex flex-wrap gap-2 border-t border-border pt-3">
          {editable ? (
            <>
              <button
                type="button"
                disabled={pending || !valid}
                onClick={() => onAccept(editedPayload())}
                className={buttonClass("primary", "sm")}
                title={valid ? "Simpan & kunci tahap ini" : "Lengkapi isian yang masih kosong dulu"}
              >
                Simpan &amp; Setujui
              </button>
              {editing ? (
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => setEditing(false)}
                  className={buttonClass("ghost", "sm")}
                >
                  Batal
                </button>
              ) : (
                <>
                  <button
                    type="button"
                    disabled={pending}
                    onClick={onGenerate}
                    className={buttonClass("secondary", "sm")}
                  >
                    {busy === stage && pending
                      ? "Memproses…"
                      : generated
                        ? "Regenerasi AI"
                        : "Generate AI"}
                  </button>
                  {generated && (
                    <button
                      type="button"
                      disabled={pending}
                      onClick={onReject}
                      className={buttonClass("danger", "sm")}
                    >
                      Tolak
                    </button>
                  )}
                </>
              )}
            </>
          ) : (
            <>
              <button
                type="button"
                disabled={pending}
                onClick={() => setEditing(true)}
                className={buttonClass("secondary", "sm")}
              >
                Edit
              </button>
              <button
                type="button"
                disabled={pending}
                onClick={onPin}
                className={buttonClass("ghost", "sm")}
              >
                {status === "pinned" ? "Tersemat" : "Sematkan"}
              </button>
            </>
          )}
        </div>
      )}
    </div>
  );
}

function LockedView({ stage, draf, estimasiWaktu }: { stage: string; draf: Draf; estimasiWaktu: string }) {
  const cpmkMap = new Map(getCpmk(draf).map((c) => [c.kode, c]));
  const subMap = new Map(getSubCpmk(draf).map((s) => [s.kode, s]));

  if (stage === "cpmk") {
    return (
      <ul className="space-y-2">
        {getCpmk(draf).map((c, i) => (
          <li key={i} className="rounded-lg border border-border bg-gray-50/50 px-3 py-2">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="brand">{c.kode}</Badge>
              {(c.taksonomi_kode ?? []).map((t) => (
                <Badge key={t} tone="warn">{t}</Badge>
              ))}
              {(c.cpl_kode ?? []).map((cpl) => (
                <Badge key={cpl} tone="neutral">{cpl}</Badge>
              ))}
            </div>
            <p className="mt-1 text-sm">{c.deskripsi}</p>
          </li>
        ))}
      </ul>
    );
  }
  if (stage === "sub_cpmk") {
    return (
      <ul className="space-y-2">
        {getSubCpmk(draf).map((s, i) => (
          <li key={i} className="rounded-lg border border-border bg-gray-50/50 px-3 py-2">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="brand">{s.kode}</Badge>
              {s.cpmk_kode && <Badge tone="neutral">← {s.cpmk_kode}</Badge>}
              {(s.taksonomi_kode ?? []).map((t) => (
                <Badge key={t} tone="warn">{t}</Badge>
              ))}
            </div>
            <p className="mt-1 text-sm">{s.deskripsi}</p>
            {(s.indikator ?? []).length > 0 && (
              <ul className="mt-1 list-inside list-disc text-xs text-muted">
                {(s.indikator ?? []).map((ind, j) => (
                  <li key={j}>{ind}</li>
                ))}
              </ul>
            )}
          </li>
        ))}
      </ul>
    );
  }
  if (stage === "mingguan") {
    return (
      <div className="overflow-x-auto rounded-lg border border-border">
        <p className="border-b border-border bg-gray-50 px-3 py-1.5 text-[11px] text-muted">
          Format 8 kolom KPT 2024 — sama dengan tampilan Dokumen RPS, Cetak (PDF), dan DOCX. Baris UTS/UAS diberi band kuning.
        </p>
        <table className="w-full border-collapse text-sm [&_td]:border [&_td]:border-border [&_th]:border [&_th]:border-border">
          <thead>
            <tr className="text-left text-xs uppercase text-muted">
              <th className="px-2 py-1.5">Mg</th>
              <th className="px-2 py-1.5">Sub-CPMK</th>
              <th className="px-2 py-1.5">Indikator</th>
              <th className="px-2 py-1.5">Kriteria &amp; Bentuk Penilaian</th>
              <th className="px-2 py-1.5">Bentuk Pembelajaran — Luring</th>
              <th className="px-2 py-1.5">Bentuk Pembelajaran — Daring</th>
              <th className="px-2 py-1.5">Materi Pembelajaran [Pustaka]</th>
              <th className="px-2 py-1.5 text-right">Bobot (%)</th>
            </tr>
          </thead>
          <tbody>
            {getMinggu(draf).map((m, i) => {
              const sub = m.sub_cpmk_kode ? subMap.get(m.sub_cpmk_kode) : undefined;
              const cpmk = sub?.cpmk_kode ? cpmkMap.get(sub.cpmk_kode) : undefined;
              const materiLower = (m.materi_pustaka ?? "").toLowerCase();
              const isUts = materiLower.includes("uts") || materiLower.includes("ujian tengah");
              const isUas = materiLower.includes("uas") || materiLower.includes("ujian akhir");
              if (isUts || isUas) {
                return (
                  <tr key={i} className="bg-amber-50">
                    <td className="px-2 py-1.5 text-center font-medium tabular-nums">{m.minggu_ke}</td>
                    <td className="px-2 py-1.5 text-center font-semibold text-amber-900" colSpan={7}>
                      {isUts ? "Evaluasi Tengah Semester (UTS)" : "Evaluasi Akhir Semester (UAS)"}
                      {m.indikator ? ` — ${m.indikator}` : ""}
                    </td>
                  </tr>
                );
              }
              return (
                <tr key={i} className="align-top">
                  <td className="px-2 py-1.5 font-medium tabular-nums">{m.minggu_ke}</td>
                  <td className="px-2 py-1.5">
                    {m.sub_cpmk_kode ? (
                      <div className="space-y-0.5">
                        <p className="font-medium text-ink">{m.sub_cpmk_kode}</p>
                        {sub?.deskripsi && <p className="text-xs text-muted">{sub.deskripsi}</p>}
                        {sub?.cpmk_kode && (
                          <p className="text-xs text-muted">
                            CPMK {sub.cpmk_kode}
                            {cpmk?.deskripsi ? `: ${cpmk.deskripsi}` : ""}
                          </p>
                        )}
                      </div>
                    ) : (
                      <span className="text-muted">—</span>
                    )}
                  </td>
                  <td className="px-2 py-1.5 text-muted">{m.indikator ?? "—"}</td>
                  <td className="px-2 py-1.5 whitespace-pre-line text-muted">{m.kriteria_penilaian ?? "—"}</td>
                  <td className="px-2 py-1.5 text-muted">
                    {m.bentuk_luring ? <div>{m.bentuk_luring}</div> : <span>—</span>}
                    {m.metode_pembelajaran && <div className="text-[11px]">Metode: {m.metode_pembelajaran}</div>}
                    {estimasiWaktu && <div className="text-[11px] italic">{estimasiWaktu}</div>}
                  </td>
                  <td className="px-2 py-1.5 text-muted">
                    {m.bentuk_daring ? <div>{m.bentuk_daring}</div> : <span>—</span>}
                    {m.pengalaman_belajar && <div className="text-[11px]">Penugasan: {m.pengalaman_belajar}</div>}
                  </td>
                  <td className="px-2 py-1.5 text-muted">{m.materi_pustaka ?? "—"}</td>
                  <td className="px-2 py-1.5 text-right tabular-nums">
                    {m.bobot_penilaian != null ? `${m.bobot_penilaian}%` : "—"}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    );
  }
  // penilaian
  const items = getKomponen(draf);
  const total = items.reduce((a, c) => a + (Number(c.bobot_persen) || 0), 0);
  return (
    <div className="space-y-2">
      <ul className="space-y-1.5">
        {items.map((k, i) => {
          const r = k.rubrik;
          const hasRubrik = !!r && (r.kriteria?.length ?? 0) > 0;
          const levels = r?.jumlah_level_skala || (r?.label_skala?.length ?? 4);
          const labels = Array.from({ length: levels }, (_, idx) => r?.label_skala?.[idx] ?? `L${idx + 1}`);
          return (
            <li key={i} className="rounded-lg border border-border bg-gray-50/50 px-3 py-2">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">{k.nama}</p>
                  <p className="text-xs text-muted">
                    {k.jenis ?? "—"} {k.sub_cpmk_kode ? `· ${k.sub_cpmk_kode}` : ""}
                    {k.sub_cpmk_kode && subMap.get(k.sub_cpmk_kode)?.deskripsi
                      ? ` (${subMap.get(k.sub_cpmk_kode)?.deskripsi})`
                      : ""}
                    {k.instrumen ? ` · ${k.instrumen}` : ""}
                  </p>
                </div>
                <Badge tone="brand">{k.bobot_persen ?? 0}%</Badge>
              </div>
              {hasRubrik && (
                <div className="mt-2 overflow-x-auto rounded-md border border-border">
                  <table className="w-full border-collapse text-xs [&_td]:border [&_td]:border-border [&_th]:border [&_th]:border-border">
                    <thead>
                      <tr className="text-left text-muted">
                        <th className="px-2 py-1 font-medium">Kriteria</th>
                        <th className="px-2 py-1 text-right font-medium">Bobot</th>
                        {labels.map((l, idx) => (
                          <th key={idx} className="px-2 py-1 font-medium">{l}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {r!.kriteria!.map((kr, ki) => (
                        <tr key={ki} className="align-top">
                          <td className="px-2 py-1 font-medium">{kr.kriteria}</td>
                          <td className="px-2 py-1 text-right tabular-nums">{kr.bobot != null ? `${kr.bobot}%` : "—"}</td>
                          {labels.map((_, idx) => (
                            <td key={idx} className="px-2 py-1 text-muted">{kr.deskriptor?.[idx] ?? "—"}</td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </li>
          );
        })}
      </ul>
      <p className={`text-right text-xs font-medium ${total === 100 ? "text-emerald-600" : "text-amber-600"}`}>
        Total bobot: {total}%
      </p>
    </div>
  );
}
