import type { ReferensiTipe } from "@/lib/api";

export const REFERENSI_TIPE: { value: ReferensiTipe; label: string }[] = [
  { value: "utama", label: "Utama" },
  { value: "pendukung", label: "Tambahan" },
  { value: "standar", label: "Standar/Farmakope" },
  { value: "jurnal", label: "Jurnal mutakhir" },
];

export const normalizeReferensiTipe = (v: unknown): ReferensiTipe =>
  REFERENSI_TIPE.some((t) => t.value === v) ? (v as ReferensiTipe) : "utama";
