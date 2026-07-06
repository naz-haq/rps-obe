"use server";

import { revalidatePath } from "next/cache";
import { apiPost, type ApiResult } from "@/lib/api";

const DEFAULT_INSTITUSI = 1;

export async function startSession(formData: FormData): Promise<ApiResult> {
  const mk_id = Number(formData.get("mk_id"));
  const sumber = (formData.get("sumber") as string) || "baru";
  const res = await apiPost("/generate-sessions", { institusi_id: DEFAULT_INSTITUSI, mk_id, sumber });
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
