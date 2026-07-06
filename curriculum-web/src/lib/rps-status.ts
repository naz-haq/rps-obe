import type { Badge } from "@/components/ui";
import type { ComponentProps } from "react";

type Tone = ComponentProps<typeof Badge>["tone"];

/** Peta status RPS → label Indonesia + tone Badge. Dipakai di semua halaman RPS. */
export const RPS_STATUS: Record<string, { label: string; tone: Tone }> = {
  draft: { label: "Draf", tone: "neutral" },
  review: { label: "Menunggu Tinjauan", tone: "warn" },
  revisi: { label: "Perlu Revisi", tone: "danger" },
  approved: { label: "Disetujui", tone: "ok" },
};

export function rpsStatusLabel(status: string): string {
  return RPS_STATUS[status]?.label ?? status;
}

export function rpsStatusTone(status: string): Tone {
  return RPS_STATUS[status]?.tone ?? "neutral";
}

/** Aksi persetujuan yang tersedia untuk sebuah status. */
export function aksiTersedia(status: string): {
  ajukan: boolean;
  setujui: boolean;
  revisi: boolean;
  tarik: boolean;
} {
  return {
    ajukan: status === "draft" || status === "revisi",
    setujui: status === "review",
    revisi: status === "review",
    tarik: status === "review",
  };
}
