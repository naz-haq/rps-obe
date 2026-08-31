"use client";

import { useEffect, useRef, useState } from "react";
import type { DokumenRujukan, MataKuliah } from "@/lib/api";
import {
  cariDokumen,
  lepasTautanDokumen,
  listDokumenTautan,
  tautkanDokumen,
  unggahDokumenUntukMk,
} from "./actions";

/**
 * Editor tautan Dokumen Rujukan (buku/artikel keilmuan) per Mata Kuliah.
 * Alur: cari dulu dokumen yang sudah ada → tautkan; kalau belum ada → unggah
 * (server mendeteksi berkas identik via hash, jadi tidak ada duplikasi).
 * Hanya tampil pada mode Edit (MK sudah punya kode).
 */
export function DokumenTautanEditor({ mk }: { mk?: MataKuliah }) {
  const kodeMk = mk?.kode_mk ?? "";
  const [linked, setLinked] = useState<DokumenRujukan[]>([]);
  const [loading, setLoading] = useState(!!kodeMk);
  const [q, setQ] = useState("");
  const [hasil, setHasil] = useState<DokumenRujukan[] | null>(null);
  const [busy, setBusy] = useState(false);
  const [pesan, setPesan] = useState<{ tone: "ok" | "err"; text: string } | null>(null);
  const [showUpload, setShowUpload] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);
  const judulRef = useRef<HTMLInputElement>(null);

  const muat = (kode: string) =>
    listDokumenTautan(kode).then((d) => {
      setLinked(d);
      setLoading(false);
    });

  useEffect(() => {
    let aktif = true;
    if (kodeMk) {
      listDokumenTautan(kodeMk).then((d) => {
        if (!aktif) return;
        setLinked(d);
        setLoading(false);
      });
    }
    return () => {
      aktif = false;
    };
  }, [kodeMk]);

  if (!kodeMk) {
    return (
      <div className="rounded-lg border border-border bg-gray-50/60 p-3">
        <span className="text-xs font-semibold text-ink">Sumber Materi (Buku/Artikel)</span>
        <p className="text-[11px] text-muted">Simpan mata kuliah dulu, lalu buka Edit untuk menautkan buku/artikel rujukan.</p>
      </div>
    );
  }

  const cari = async () => {
    if (!q.trim()) return;
    setBusy(true);
    setPesan(null);
    const semua = await cariDokumen(q.trim());
    const idTertaut = new Set(linked.map((d) => d.id));
    setHasil(semua.filter((d) => !idTertaut.has(d.id)));
    setBusy(false);
  };

  const tautkan = async (d: DokumenRujukan) => {
    setBusy(true);
    const res = await tautkanDokumen(d.id, kodeMk);
    if (res.ok) {
      setPesan({ tone: "ok", text: `"${d.judul ?? d.file_asal}" ditautkan ke ${kodeMk}.` });
      setHasil((h) => (h ? h.filter((x) => x.id !== d.id) : h));
      await muat(kodeMk);
    } else {
      setPesan({ tone: "err", text: res.message ?? "Gagal menautkan dokumen." });
    }
    setBusy(false);
  };

  const lepas = async (d: DokumenRujukan) => {
    setBusy(true);
    const res = await lepasTautanDokumen(d.id, kodeMk);
    if (res.ok) {
      setLinked((ls) => ls.filter((x) => x.id !== d.id));
      setPesan({ tone: "ok", text: "Tautan dilepas (dokumen tetap tersimpan di pustaka dokumen)." });
    } else {
      setPesan({ tone: "err", text: res.message ?? "Gagal melepas tautan." });
    }
    setBusy(false);
  };

  const kirimKePustaka = (d: DokumenRujukan) => {
    const sitasi = d.judul ?? d.file_asal;
    if (!sitasi) return;
    window.dispatchEvent(new CustomEvent("rps:add-referensi", { detail: { sitasi, tipe: "utama" } }));
    setPesan({ tone: "ok", text: `"${sitasi}" ditambahkan ke daftar Pustaka di atas — lengkapi penulis/tahun/penerbit, lalu simpan MK.` });
  };

  const unggah = async () => {
    const file = fileRef.current?.files?.[0];
    if (!file) {
      setPesan({ tone: "err", text: "Pilih berkas dulu." });
      return;
    }
    if (file.size > 50 * 1024 * 1024) {
      setPesan({ tone: "err", text: "Ukuran berkas maksimal 50 MB." });
      return;
    }
    setBusy(true);
    setPesan(null);
    const fd = new FormData();
    fd.set("file", file);
    fd.set("judul", judulRef.current?.value ?? "");
    fd.set("jenis", "buku");
    fd.set("sumber_konten", "1");
    const res = await unggahDokumenUntukMk(kodeMk, fd);
    if (res.ok) {
      setPesan({
        tone: "ok",
        text: res.dedup
          ? (res.message ?? "Berkas identik sudah ada — dokumen lama langsung ditautkan tanpa duplikasi.")
          : "Dokumen diunggah, ditautkan, dan sedang di-index untuk AI.",
      });
      if (fileRef.current) fileRef.current.value = "";
      if (judulRef.current) judulRef.current.value = "";
      setShowUpload(false);
      await muat(kodeMk);
    } else {
      setPesan({ tone: "err", text: res.message ?? "Gagal mengunggah dokumen." });
    }
    setBusy(false);
  };

  return (
    <div className="rounded-lg border border-border bg-gray-50/60 p-3">
      <div className="mb-2">
        <span className="text-xs font-semibold text-ink">Sumber Materi (Buku/Artikel Tertaut)</span>
        <p className="text-[11px] text-muted">
          Cari dulu — bila buku/artikel sudah pernah diunggah dosen lain, cukup tautkan. AI memprioritaskan dokumen tertaut saat generate RPS MK ini.
        </p>
      </div>

      {pesan && (
        <p
          className={`mb-2 rounded border px-2 py-1 text-[11px] ${
            pesan.tone === "ok" ? "border-emerald-200 bg-emerald-50 text-emerald-800" : "border-red-200 bg-red-50 text-red-700"
          }`}
        >
          {pesan.text}
        </p>
      )}

      {loading ? (
        <p className="py-1 text-xs text-muted">Memuat…</p>
      ) : linked.length === 0 ? (
        <p className="py-1 text-xs text-muted">Belum ada dokumen tertaut ke {kodeMk}.</p>
      ) : (
        <ul className="mb-2 space-y-1">
          {linked.map((d) => (
            <li key={d.id} className="flex items-center justify-between gap-2 rounded-md border border-border bg-surface px-2 py-1">
              <span className="min-w-0 truncate text-xs text-ink">
                📖 {d.judul ?? d.file_asal ?? `Dokumen #${d.id}`}
                <span className="ml-1 text-[10px] text-muted">
                  {d.status_indexing === "indexed" ? `(${d.chunk_count ?? 0} potongan terindeks)` : `(${d.status_indexing})`}
                </span>
              </span>
              <span className="flex shrink-0 items-center gap-2">
                <button
                  type="button"
                  onClick={() => kirimKePustaka(d)}
                  disabled={busy}
                  className="text-[11px] font-medium text-brand-700 hover:underline disabled:opacity-50"
                  title="Tambahkan judul dokumen ini ke daftar Pustaka/Referensi"
                >
                  → Pustaka
                </button>
                <button
                  type="button"
                  onClick={() => lepas(d)}
                  disabled={busy}
                  className="text-[11px] text-red-600 hover:underline disabled:opacity-50"
                >
                  Lepas
                </button>
              </span>
            </li>
          ))}
        </ul>
      )}

      <div className="flex items-center gap-2">
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              void cari();
            }
          }}
          placeholder="Cari judul buku/artikel yang sudah ada…"
          className="min-w-0 flex-1 rounded-md border border-border bg-surface px-2 py-1 text-xs text-ink outline-none focus-ring"
        />
        <button
          type="button"
          onClick={cari}
          disabled={busy || !q.trim()}
          className="shrink-0 rounded-md border border-border bg-surface px-2 py-1 text-xs font-medium text-ink hover:bg-gray-100 disabled:opacity-50"
        >
          Periksa
        </button>
        <button
          type="button"
          onClick={() => setShowUpload((v) => !v)}
          className="shrink-0 rounded-md border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100"
        >
          {showUpload ? "Batal Unggah" : "+ Unggah Baru"}
        </button>
      </div>

      {hasil !== null && (
        <div className="mt-2">
          {hasil.length === 0 ? (
            <p className="text-[11px] text-muted">Tidak ada dokumen cocok yang belum tertaut. Silakan unggah baru.</p>
          ) : (
            <ul className="space-y-1">
              {hasil.map((d) => (
                <li key={d.id} className="flex items-center justify-between gap-2 rounded-md border border-dashed border-border px-2 py-1">
                  <span className="min-w-0 truncate text-xs text-ink">
                    {d.judul ?? d.file_asal ?? `Dokumen #${d.id}`}
                    {typeof d.mk_tautan_count === "number" && d.mk_tautan_count > 0 && (
                      <span className="ml-1 text-[10px] text-muted">(dipakai {d.mk_tautan_count} MK)</span>
                    )}
                  </span>
                  <button
                    type="button"
                    onClick={() => tautkan(d)}
                    disabled={busy}
                    className="shrink-0 text-[11px] font-medium text-brand-700 hover:underline disabled:opacity-50"
                  >
                    ✓ Tautkan
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {showUpload && (
        <div className="mt-2 space-y-2 rounded-md border border-border bg-surface p-2">
          <input
            ref={judulRef}
            placeholder="Judul buku/artikel (mis. Katzung — Basic & Clinical Pharmacology)"
            className="w-full rounded-md border border-border bg-surface px-2 py-1 text-xs text-ink outline-none focus-ring"
          />
          <input ref={fileRef} type="file" accept=".pdf,.docx,.txt,.md,.csv" className="w-full text-xs text-ink" />
          <div className="flex items-center justify-between">
            <p className="text-[10px] text-muted">PDF/DOCX/TXT/MD, maks 50 MB. Duplikat terdeteksi otomatis via hash.</p>
            <button
              type="button"
              onClick={unggah}
              disabled={busy}
              className="rounded-md bg-brand-600 px-2 py-1 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-50"
            >
              {busy ? "Mengunggah…" : "Unggah & Tautkan"}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
