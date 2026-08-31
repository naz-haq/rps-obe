"use server";

import { revalidatePath } from "next/cache";
import { apiPost, apiPatch, apiDelete, type ApiResult } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

export async function startSession(formData: FormData): Promise<ApiResult> {
  const mk_id = Number(formData.get("mk_id"));
  const sumber = (formData.get("sumber") as string) || "baru";
  const res = await apiPost("/generate-sessions", {
    institusi_id: DEFAULT_INSTITUSI,
    mk_id,
    sumber,
    kompetensi_khusus: ((formData.get("kompetensi_khusus") as string) || "").trim() || null,
    bok: ((formData.get("bok") as string) || "").trim() || null,
    bahan_kajian_khusus: ((formData.get("bahan_kajian_khusus") as string) || "").trim() || null,
  });
  revalidatePath("/generator");
  return res;
}

/** Ubah rujukan tambahan dosen pada sesi yang sudah berjalan. */
export async function updateKonteks(
  id: number,
  konteks: { kompetensi_khusus?: string; bok?: string; bahan_kajian_khusus?: string }
): Promise<ApiResult> {
  const res = await apiPatch(`/generate-sessions/${id}/konteks`, konteks);
  revalidatePath(`/generator/${id}`);
  return res;
}

/** Simpan detail MK yang menjadi tanggung jawab dosen (deskripsi + pustaka). */
export async function saveDetailMk(sessionId: number, formData: FormData): Promise<ApiResult> {
  const mkId = Number(formData.get("mk_id"));
  const institusiId = Number(formData.get("institusi_id")) || DEFAULT_INSTITUSI;
  const kodeMk = (formData.get("kode_mk") as string) || "";

  const res = await apiPatch(`/mata-kuliah/${mkId}`, {
    deskripsi_singkat: ((formData.get("deskripsi_singkat") as string) || "").trim() || null,
  });
  if (!res.ok) return res;

  // Sinkron pustaka bila editor mengirim referensi_json.
  const raw = (formData.get("referensi_json") as string) || "";
  if (raw.trim() && kodeMk) {
    try {
      const arr = JSON.parse(raw) as { tipe?: string; sitasi?: string }[];
      const items = (Array.isArray(arr) ? arr : [])
        .map((r) => ({ tipe: r.tipe === "pendukung" ? "pendukung" : "utama", sitasi: String(r.sitasi ?? "").trim() }))
        .filter((r) => r.sitasi !== "");
      const sync = await apiPost("/referensi/sync", { institusi_id: institusiId, kode_mk: kodeMk, items });
      if (!sync.ok) return sync;
    } catch {
      // referensi_json korup → biarkan; deskripsi sudah tersimpan
    }
  }

  revalidatePath(`/generator/${sessionId}`);
  return res;
}

export async function deleteSession(formData: FormData): Promise<ApiResult> {
  const id = formData.get("id") as string;
  const res = await apiDelete(`/generate-sessions/${id}`);
  revalidatePath("/generator");
  return res;
}

export async function generateStage(id: number, stage: string): Promise<ApiResult> {
  const res = await apiPost(`/generate-sessions/${id}/generate`, { stage });
  revalidatePath(`/generator/${id}`);
  return res;
}

export async function acceptStage(id: number, stage: string, edited?: unknown): Promise<ApiResult> {
  const res = await apiPost(`/generate-sessions/${id}/accept`, { stage, edited });
  revalidatePath(`/generator/${id}`);
  return res;
}

export async function rejectStage(id: number, stage: string): Promise<ApiResult> {
  const res = await apiPost(`/generate-sessions/${id}/reject`, { stage });
  revalidatePath(`/generator/${id}`);
  return res;
}

export async function pinStage(id: number, stage: string): Promise<ApiResult> {
  const res = await apiPost(`/generate-sessions/${id}/pin`, { stage });
  revalidatePath(`/generator/${id}`);
  return res;
}

export async function commitSession(id: number): Promise<ApiResult> {
  const res = await apiPost(`/generate-sessions/${id}/commit`);
  revalidatePath(`/generator/${id}`);
  revalidatePath("/generator");
  revalidatePath("/rps");
  return res;
}

export type AuditIsu = {
  tipe: string;
  kategori: string;
  kode_target: string;
  pesan: string;
  saran: string;
};

export type AuditHasil = {
  skor_keseluruhan: number;
  status: string;
  umpan_balik: string;
  isu: AuditIsu[];
  sumber_prompt?: string;
};

export async function runAudit(
  id: number,
): Promise<{ ok: boolean; message?: string; data?: AuditHasil }> {
  // send() sudah membuka lapisan { data: ... } terluar dari respons.
  const res = await apiPost<AuditHasil>(`/generate-sessions/${id}/audit`, {});
  if (!res.ok) return { ok: false, message: res.message };
  return { ok: true, data: res.data };
}

export type ChatMessage = { sender: "user" | "ai"; text: string };

export async function chatConsult(
  sessionId: number,
  messages: ChatMessage[],
): Promise<{ ok: boolean; message?: string; text?: string }> {
  const res = await apiPost<{ text: string }>(`/rps/ai/chat`, {
    institusi_id: DEFAULT_INSTITUSI,
    messages,
    generate_session_id: sessionId,
  });
  if (!res.ok) return { ok: false, message: res.message };
  return { ok: true, text: res.data?.text ?? "" };
}
