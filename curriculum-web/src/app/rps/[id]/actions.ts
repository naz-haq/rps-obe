"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPut, type ApiResult, type RincianPertemuan } from "@/lib/api";

type Aksi = "ajukan" | "setujui" | "revisi" | "tarik";

export async function aksiPersetujuan(input: {
  id: number;
  aksi: Aksi;
  catatan?: string;
  actor_nama?: string;
}): Promise<ApiResult> {
  const res = await apiPost(`/rps-versions/${input.id}/${input.aksi}`, {
    catatan: input.catatan ?? undefined,
    actor_nama: input.actor_nama ?? undefined,
  });
  revalidatePath(`/rps/${input.id}`);
  revalidatePath("/rps");
  revalidatePath("/persetujuan");
  return res;
}

/** Generate lanjutan: rincian per-pertemuan dari rencana mingguan (MK blok/profesi). */
export async function generateRincianPertemuan(id: number): Promise<ApiResult> {
  const res = await apiPost(`/rps-versions/${id}/generate-pertemuan`);
  revalidatePath(`/rps/${id}`);
  revalidatePath(`/rps/${id}/pertemuan`);
  return res;
}

/** Simpan/edit MANUAL rincian pertemuan satu pekan (alternatif jalur AI). */
export async function simpanRincianPertemuan(
  id: number,
  mingguKe: number,
  rincian: Partial<RincianPertemuan>[],
): Promise<ApiResult> {
  const res = await apiPut(`/rps-versions/${id}/minggu/${mingguKe}/rincian`, { rincian });
  revalidatePath(`/rps/${id}/pertemuan`);
  return res;
}

/** Kelengkapan dokumen format OBE. Field kosong = sistem memakai nilai turunan. */
export async function simpanKelengkapan(id: number, fd: FormData): Promise<ApiResult> {
  const teks = (k: string) => ((fd.get(k) as string | null) ?? "").trim() || null;
  const orang = (p: string) => ({ nama: teks(`${p}_nama`) ?? "", nidn: teks(`${p}_nidn`) ?? "" });
  const pilihan = (prefix: string) => {
    const out: Record<string, string> = {};
    for (const [k, v] of fd.entries()) {
      if (k.startsWith(prefix) && typeof v === "string" && v !== "") out[k.slice(prefix.length)] = v;
    }
    return out;
  };
  const luring = teks("proporsi_luring");

  const res = await apiPut(`/rps-versions/${id}/kelengkapan`, {
    kode_dokumen: teks("kode_dokumen"),
    tanggal_penyusunan: teks("tanggal_penyusunan"),
    tahun_akademik: teks("tahun_akademik"),
    berlaku_mulai: teks("berlaku_mulai"),
    kelengkapan: {
      otorisasi: {
        koordinator_mk: orang("koordinator_mk"),
        koordinator_bk: orang("koordinator_bk"),
        ketua_prodi: orang("ketua_prodi"),
      },
      media_pembelajaran: teks("media_pembelajaran"),
      lms: teks("lms"),
      pengalaman_belajar_utama: teks("pengalaman_belajar_utama"),
      proporsi_luring: luring === null ? null : Number(luring),
      sumber_belajar_utama: teks("sumber_belajar_utama"),
      peran_cpl: pilihan("peran_cpl__"),
      lampiran: Object.fromEntries(Object.entries(pilihan("lampiran__")).map(([k, v]) => [k, v === "ada"])),
    },
  });
  revalidatePath(`/rps/${id}`);
  return res;
}
