"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useToast } from "@/components/toast";
import type { ApiResult } from "@/lib/api";

/**
 * Efek standar hasil server action pada form:
 * - state.ok  -> toast sukses, refresh data, jalankan onSuccess (mis. tutup modal)
 * - state gagal -> toast error (pakai message dari server)
 *
 * Contoh:
 *   const [state, action] = useActionState(...);
 *   useActionResult(state, { onSuccess: close, successMessage: "Data tersimpan." });
 */
export function useActionResult(
  state: ApiResult | null | undefined,
  opts: { onSuccess?: () => void; successMessage?: string; refresh?: boolean } = {},
) {
  const router = useRouter();
  const toast = useToast();
  useEffect(() => {
    if (!state) return;
    if (state.ok) {
      toast({ type: "success", message: opts.successMessage ?? "Perubahan berhasil disimpan." });
      if (opts.refresh !== false) router.refresh();
      opts.onSuccess?.();
    } else {
      toast({ type: "error", message: state.message ?? "Aksi gagal diproses. Coba lagi." });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);
}
