"use client";

import { useMemo, useState, useTransition } from "react";
import { Card, CardBody, Badge, buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { KonfigurasiAturan, BobotKomponen } from "@/lib/api";
import { saveAturan } from "./actions";

type NilaiMap = Record<string, number>;

function pick(list: KonfigurasiAturan[], jenis: string): Record<string, unknown> {
  return list.find((k) => k.jenis_aturan === jenis)?.nilai ?? {};
}

function NumberInput({
  label,
  value,
  onChange,
  suffix,
}: {
  label: string;
  value: number | "";
  onChange: (v: number | "") => void;
  suffix?: string;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-ink">{label}</span>
      <div className="flex items-center gap-2">
        <input
          type="number"
          min={0}
          value={value}
          onChange={(e) => onChange(e.target.value === "" ? "" : Number(e.target.value))}
          className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink outline-none focus-ring"
        />
        {suffix && <span className="whitespace-nowrap text-xs text-muted">{suffix}</span>}
      </div>
    </label>
  );
}

function SaveBar({
  jenis,
  nilai,
  disabled,
}: {
  jenis: string;
  nilai: Record<string, unknown>;
  disabled?: boolean;
}) {
  const [pending, startTransition] = useTransition();
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const toast = useToast();

  const save = () => {
    setMsg(null);
    startTransition(async () => {
      const res = await saveAturan({ jenis_aturan: jenis, nilai });
      if (res.ok) {
        toast({ type: "success", message: "Aturan tersimpan." });
        setMsg({ ok: true, text: "Tersimpan." });
      } else {
        toast({ type: "error", message: res.message ?? "Gagal menyimpan." });
        setMsg({ ok: false, text: res.message ?? "Gagal menyimpan." });
      }
    });
  };

  return (
    <div className="flex items-center justify-end gap-3 pt-1">
      {msg && (
        <span className={`text-xs ${msg.ok ? "text-emerald-600" : "text-red-600"}`}>{msg.text}</span>
      )}
      <button type="button" onClick={save} disabled={pending || disabled} className={buttonClass("primary", "sm")}>
        {pending ? "Menyimpan…" : "Simpan"}
      </button>
    </div>
  );
}

/** Kartu editor komponen bobot dinamis (nama + %), harus berjumlah 100%. */
function BobotCard({
  jenis,
  title,
  subtitle,
  initial,
  fallback,
}: {
  jenis: string;
  title: string;
  subtitle: string;
  initial: Record<string, unknown>;
  fallback: BobotKomponen[];
}) {
  const initialKomponen = useMemo<BobotKomponen[]>(() => {
    const raw = initial.komponen;
    if (Array.isArray(raw) && raw.length > 0) {
      return raw.map((k) => ({
        nama: String((k as BobotKomponen).nama ?? ""),
        bobot: Number((k as BobotKomponen).bobot ?? 0),
      }));
    }
    return fallback;
  }, [initial, fallback]);

  const [komponen, setKomponen] = useState<BobotKomponen[]>(initialKomponen);

  const total = komponen.reduce((s, k) => s + (Number.isFinite(k.bobot) ? k.bobot : 0), 0);
  const valid = total === 100;

  const setRow = (i: number, patch: Partial<BobotKomponen>) =>
    setKomponen((prev) => prev.map((k, idx) => (idx === i ? { ...k, ...patch } : k)));
  const addRow = () => setKomponen((prev) => [...prev, { nama: "", bobot: 0 }]);
  const removeRow = (i: number) => setKomponen((prev) => prev.filter((_, idx) => idx !== i));

  const nilai = {
    komponen: komponen
      .filter((k) => k.nama.trim() !== "")
      .map((k) => ({ nama: k.nama.trim(), bobot: Number(k.bobot) || 0 })),
    total,
  };

  return (
    <Card className="animate-fade-up">
      <div className="border-b border-border px-5 py-3.5">
        <h2 className="text-sm font-semibold text-ink">{title}</h2>
        <p className="mt-0.5 text-xs text-muted">{subtitle}</p>
      </div>
      <CardBody className="space-y-3">
        <div className="space-y-2">
          <div className="flex items-center gap-2 px-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">
            <span className="flex-1">Komponen penilaian</span>
            <span className="w-20 text-right">Bobot</span>
            <span className="w-6" />
          </div>
          {komponen.map((k, i) => (
            <div key={i} className="flex items-center gap-2">
              <input
                value={k.nama}
                onChange={(e) => setRow(i, { nama: e.target.value })}
                placeholder="mis. Ujian Akhir"
                className="flex-1 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
              />
              <div className="flex w-20 items-center gap-1">
                <input
                  type="number"
                  min={0}
                  max={100}
                  value={k.bobot}
                  onChange={(e) => setRow(i, { bobot: e.target.value === "" ? 0 : Number(e.target.value) })}
                  className="w-full rounded-lg border border-border bg-surface px-2 py-1.5 text-right text-sm text-ink outline-none focus-ring"
                />
                <span className="text-xs text-muted">%</span>
              </div>
              <button
                type="button"
                onClick={() => removeRow(i)}
                aria-label="Hapus komponen"
                className="grid h-7 w-6 place-items-center rounded-md text-muted hover:bg-red-50 hover:text-red-600"
              >
                ✕
              </button>
            </div>
          ))}
        </div>

        <button type="button" onClick={addRow} className="text-xs font-medium text-brand-700 hover:underline">
          + Tambah komponen
        </button>

        <div
          className={`flex items-center justify-between rounded-lg border px-3 py-2 text-xs ${
            valid
              ? "border-emerald-200 bg-emerald-50 text-emerald-700"
              : "border-amber-200 bg-amber-50 text-amber-700"
          }`}
        >
          <span>{valid ? "Total bobot 100% — valid." : "Total bobot harus tepat 100%."}</span>
          <Badge tone={valid ? "ok" : "warn"}>{total}%</Badge>
        </div>

        <SaveBar jenis={jenis} nilai={nilai} disabled={!valid} />
      </CardBody>
    </Card>
  );
}

const DEFAULT_BOBOT_TEORI: BobotKomponen[] = [
  { nama: "Kehadiran & Keaktifan", bobot: 10 },
  { nama: "Tugas", bobot: 20 },
  { nama: "Ujian Tengah Semester (UTS)", bobot: 30 },
  { nama: "Ujian Akhir Semester (UAS)", bobot: 40 },
];

const DEFAULT_BOBOT_PRAKTIKUM: BobotKomponen[] = [
  { nama: "Kehadiran & Keaktifan", bobot: 10 },
  { nama: "Pretest / Responsi", bobot: 20 },
  { nama: "Laporan Praktikum", bobot: 30 },
  { nama: "Ujian Akhir Praktikum", bobot: 40 },
];

export function KonfigurasiForms({ list }: { list: KonfigurasiAturan[] }) {
  // Jumlah minggu
  const jm = pick(list, "jumlah_minggu") as NilaiMap;
  const [mingguEfektif, setMingguEfektif] = useState<number | "">(jm.minggu_efektif ?? 16);
  const [mingguUts, setMingguUts] = useState<number | "">(jm.minggu_uts ?? 8);
  const [mingguUas, setMingguUas] = useState<number | "">(jm.minggu_uas ?? 16);

  // Konversi SKS
  const ks = pick(list, "konversi_sks") as NilaiMap;
  const [teoriTatap, setTeoriTatap] = useState<number | "">(ks.teori_tatap_muka ?? 50);
  const [teoriStruktur, setTeoriStruktur] = useState<number | "">(ks.teori_terstruktur ?? 60);
  const [teoriMandiri, setTeoriMandiri] = useState<number | "">(ks.teori_mandiri ?? 60);
  const [praktik, setPraktik] = useState<number | "">(ks.praktik ?? 170);

  const num = (v: number | "") => (v === "" ? 0 : v);
  const teoriTotal = num(teoriTatap) + num(teoriStruktur) + num(teoriMandiri);
  const efektif = num(mingguEfektif) || 16;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {/* Jumlah minggu */}
        <Card className="animate-fade-up">
          <div className="border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Jumlah Minggu</h2>
            <p className="mt-0.5 text-xs text-muted">Minggu perkuliahan efektif per semester dan posisi ujian.</p>
          </div>
          <CardBody className="space-y-3">
            <NumberInput label="Minggu efektif" value={mingguEfektif} onChange={setMingguEfektif} suffix="minggu" />
            <div className="grid grid-cols-2 gap-3">
              <NumberInput label="Minggu UTS" value={mingguUts} onChange={setMingguUts} suffix="ke-" />
              <NumberInput label="Minggu UAS" value={mingguUas} onChange={setMingguUas} suffix="ke-" />
            </div>
            <SaveBar
              jenis="jumlah_minggu"
              nilai={{
                minggu_efektif: num(mingguEfektif),
                minggu_uts: num(mingguUts),
                minggu_uas: num(mingguUas),
              }}
            />
          </CardBody>
        </Card>

        {/* Konversi SKS */}
        <Card className="animate-fade-up">
          <div className="border-b border-border px-5 py-3.5">
            <h2 className="text-sm font-semibold text-ink">Konversi SKS → Jam</h2>
            <p className="mt-0.5 text-xs text-muted">Menit per 1 SKS per minggu (SN-Dikti; tenant boleh override).</p>
          </div>
          <CardBody className="space-y-3">
            <p className="text-xs font-medium text-ink">Teori / Kuliah (per 1 SKS/minggu)</p>
            <div className="grid grid-cols-3 gap-2">
              <NumberInput label="Tatap muka" value={teoriTatap} onChange={setTeoriTatap} suffix="′" />
              <NumberInput label="Terstruktur" value={teoriStruktur} onChange={setTeoriStruktur} suffix="′" />
              <NumberInput label="Mandiri" value={teoriMandiri} onChange={setTeoriMandiri} suffix="′" />
            </div>
            <NumberInput label="Praktikum (per 1 SKS/minggu)" value={praktik} onChange={setPraktik} suffix="′" />
            <div className="rounded-lg border border-border bg-gray-50 px-3 py-2 text-xs text-muted">
              <div className="flex items-center justify-between">
                <span>Teori 1 SKS / minggu</span>
                <Badge tone="brand">{teoriTotal}′</Badge>
              </div>
              <div className="mt-1 flex items-center justify-between">
                <span>Teori 1 SKS / semester (×{efektif})</span>
                <span className="font-medium text-ink">{Math.round((teoriTotal * efektif) / 60)} jam</span>
              </div>
              <div className="mt-1 flex items-center justify-between">
                <span>Praktik 1 SKS / semester (×{efektif})</span>
                <span className="font-medium text-ink">{Math.round((num(praktik) * efektif) / 60)} jam</span>
              </div>
            </div>
            <SaveBar
              jenis="konversi_sks"
              nilai={{
                teori_tatap_muka: num(teoriTatap),
                teori_terstruktur: num(teoriStruktur),
                teori_mandiri: num(teoriMandiri),
                praktik: num(praktik),
              }}
            />
          </CardBody>
        </Card>
      </div>

      {/* Bobot penilaian terpisah: MK Kuliah vs MK Praktikum */}
      <div>
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
          Bobot Penilaian per Jenis Mata Kuliah
        </p>
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <BobotCard
            jenis="bobot_teori"
            title="MK Kuliah / Teori (murni)"
            subtitle="Komponen penilaian untuk mata kuliah teori. Total harus 100%."
            initial={pick(list, "bobot_teori")}
            fallback={DEFAULT_BOBOT_TEORI}
          />
          <BobotCard
            jenis="bobot_praktikum"
            title="MK Praktikum"
            subtitle="Komponen penilaian untuk mata kuliah praktikum (terpisah). Total harus 100%."
            initial={pick(list, "bobot_praktikum")}
            fallback={DEFAULT_BOBOT_PRAKTIKUM}
          />
        </div>
      </div>
    </div>
  );
}
