// Bentuk data draf per-tahap yang dipakai builder, self-check, & matriks.

export type CpmkItem = {
  kode: string;
  deskripsi: string;
  cpl_kode?: string[];
  taksonomi_kode?: string[];
};

export type SubCpmkItem = {
  kode: string;
  cpmk_kode?: string;
  deskripsi: string;
  taksonomi_kode?: string[];
  indikator?: string[];
};

export type MingguItem = {
  minggu_ke: number;
  sub_cpmk_kode?: string;
  indikator?: string;
  kriteria_penilaian?: string;
  metode_pembelajaran?: string;
  bentuk_luring?: string;
  bentuk_daring?: string;
  pengalaman_belajar?: string;
  materi_pustaka?: string;
  bobot_penilaian?: number;
};

export type RubrikKriteriaItem = {
  kriteria: string;
  bobot?: number;
  deskriptor?: string[];
};

export type RubrikItem = {
  jenis?: string;
  jumlah_level_skala?: number;
  label_skala?: string[];
  kriteria?: RubrikKriteriaItem[];
};

export type KomponenItem = {
  nama: string;
  jenis?: string;
  instrumen?: string;
  bobot_persen?: number;
  sub_cpmk_kode?: string;
  minggu_ke?: number;
  rubrik?: RubrikItem | null;
};

export type Draf = {
  cpmk?: { cpmk?: CpmkItem[] };
  sub_cpmk?: { sub_cpmk?: SubCpmkItem[] };
  mingguan?: { minggu?: MingguItem[] };
  penilaian?: { komponen?: KomponenItem[] };
};

/** Paksa taksonomi_kode (bisa string tunggal lama atau array) menjadi list bersih. */
export function coerceTaksonomi(raw: unknown): string[] {
  if (raw == null || raw === "") return [];
  const items = Array.isArray(raw) ? raw : [raw];
  const out: string[] = [];
  for (const k of items) {
    const s = String(k).trim();
    if (s && !out.includes(s)) out.push(s);
  }
  return out;
}

export function getCpmk(draf: Draf): CpmkItem[] {
  return (draf.cpmk?.cpmk ?? []).map((c) => ({
    ...c,
    taksonomi_kode: coerceTaksonomi((c as { taksonomi_kode?: unknown }).taksonomi_kode),
  }));
}
export function getSubCpmk(draf: Draf): SubCpmkItem[] {
  return (draf.sub_cpmk?.sub_cpmk ?? []).map((s) => ({
    ...s,
    taksonomi_kode: coerceTaksonomi((s as { taksonomi_kode?: unknown }).taksonomi_kode),
  }));
}
export function getMinggu(draf: Draf): MingguItem[] {
  return (draf.mingguan?.minggu ?? []).map((m) => {
    const legacy = m as MingguItem & { bahan_kajian?: string };
    return {
      ...m,
      materi_pustaka: m.materi_pustaka ?? legacy.bahan_kajian,
    };
  });
}
export function getKomponen(draf: Draf): KomponenItem[] {
  return draf.penilaian?.komponen ?? [];
}

/** Ambil angka level taksonomi dari kode seperti "C4"/"A3"/"P2" → 4/3/2. */
export function taksonomiLevel(kode?: string): number {
  if (!kode) return 0;
  const m = kode.match(/[CAP](\d)/i);
  return m ? parseInt(m[1], 10) : 0;
}

/** Level taksonomi tertinggi dari sekumpulan kode. */
export function maxTaksonomiLevel(kodes?: string[]): number {
  if (!kodes || kodes.length === 0) return 0;
  return Math.max(0, ...kodes.map((k) => taksonomiLevel(k)));
}

/**
 * Validasi kelengkapan isi tiap tahap sebelum "Simpan & Setujui".
 * Mengembalikan daftar masalah (array kosong = valid/siap disimpan).
 */
export function validateStage(
  stage: string,
  data: { cpmk: CpmkItem[]; sub: SubCpmkItem[]; minggu: MingguItem[]; komponen: KomponenItem[] },
): string[] {
  const issues: string[] = [];
  const s = (v?: string) => (v ?? "").trim();

  if (stage === "cpmk") {
    if (data.cpmk.length === 0) {
      issues.push("Belum ada CPMK. Tambahkan minimal satu baris atau klik Generate AI.");
    }
    data.cpmk.forEach((c, i) => {
      if (!s(c.kode)) issues.push(`CPMK #${i + 1}: kode masih kosong.`);
      if (!s(c.deskripsi)) issues.push(`CPMK #${i + 1}: deskripsi masih kosong.`);
      if (!(c.cpl_kode ?? []).some((x) => s(x))) issues.push(`CPMK #${i + 1}: belum dipetakan ke CPL.`);
    });
  }

  if (stage === "sub_cpmk") {
    if (data.sub.length === 0) {
      issues.push("Belum ada Sub-CPMK. Tambahkan minimal satu baris atau klik Generate AI.");
    }
    data.sub.forEach((x, i) => {
      if (!s(x.kode)) issues.push(`Sub-CPMK #${i + 1}: kode masih kosong.`);
      if (!s(x.deskripsi)) issues.push(`Sub-CPMK #${i + 1}: deskripsi masih kosong.`);
    });
  }

  if (stage === "mingguan") {
    if (data.minggu.length === 0) {
      issues.push("Belum ada rencana mingguan. Tambahkan minggu atau klik Generate AI.");
    }
    const terisi = (m: MingguItem) =>
      s(m.sub_cpmk_kode) ||
      s(m.indikator) ||
      s(m.kriteria_penilaian) ||
      s(m.metode_pembelajaran) ||
      s(m.bentuk_luring) ||
      s(m.bentuk_daring) ||
      s(m.pengalaman_belajar) ||
      s(m.materi_pustaka);
    const kosong = data.minggu.filter((m) => !terisi(m));
    if (data.minggu.length > 0 && kosong.length === data.minggu.length) {
      issues.push("Semua minggu masih kosong. Isi materi/indikator tiap pertemuan atau klik Generate AI.");
    } else if (kosong.length > 0) {
      const daftar = kosong.map((m) => m.minggu_ke).join(", ");
      issues.push(`${kosong.length} minggu masih kosong (minggu ${daftar}). Lengkapi atau hapus baris kosong.`);
    }
  }

  if (stage === "penilaian") {
    if (data.komponen.length === 0) {
      issues.push("Belum ada komponen penilaian. Tambahkan minimal satu baris atau klik Generate AI.");
    }
    data.komponen.forEach((k, i) => {
      if (!s(k.nama)) issues.push(`Komponen #${i + 1}: nama masih kosong.`);
    });
    if (data.komponen.length > 0) {
      const total = data.komponen.reduce((a, k) => a + (Number(k.bobot_persen) || 0), 0);
      if (total !== 100) issues.push(`Total bobot komponen ${total}% — harus tepat 100%.`);
    }
  }

  return issues;
}
