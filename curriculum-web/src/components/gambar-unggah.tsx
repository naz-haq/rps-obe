"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import { useConfirm } from "@/components/confirm";
import { FileField } from "@/components/file-field";
import type { ApiResult } from "@/lib/api";

/** Kelola satu gambar (logo/tanda tangan): pratinjau tersimpan, unggah pengganti, hapus. */
export function GambarUnggah({
  src,
  alt,
  kosong,
  unggah,
  hapus,
  bolehUbah = true,
  latar = "bg-white",
}: {
  src: string | null;
  alt: string;
  kosong: string;
  unggah: (fd: FormData) => Promise<ApiResult>;
  hapus: () => Promise<ApiResult>;
  bolehUbah?: boolean;
  latar?: string;
}) {
  const router = useRouter();
  const toast = useToast();
  const { confirm } = useConfirm();
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [formKey, setFormKey] = useState(0);

  const jalankan = (aksi: () => Promise<ApiResult>, sukses: string) =>
    start(async () => {
      setError(null);
      const res = await aksi();
      if (res.ok) {
        toast({ type: "success", message: sukses });
        setFormKey((k) => k + 1);
        router.refresh();
      } else {
        setError(res.message ?? "Gagal memproses berkas.");
      }
    });

  return (
    <div className="space-y-3">
      <div className={`grid h-28 place-items-center rounded-xl border border-border ${latar}`}>
        {src ? (
          // eslint-disable-next-line @next/next/no-img-element -- gambar privat via proksi backend
          <img src={src} alt={alt} className="max-h-24 max-w-[80%] object-contain" />
        ) : (
          <span className="text-xs text-muted">{kosong}</span>
        )}
      </div>
      {bolehUbah && (
        <form
          key={formKey}
          action={(fd) => {
            if (!(fd.get("file") as File | null)?.size) {
              setError("Pilih berkas gambar terlebih dahulu.");
              return;
            }
            jalankan(() => unggah(fd), "Gambar tersimpan.");
          }}
          className="space-y-2"
        >
          <FileField name="file" accept="image/png,image/jpeg,image/webp" maxMb={5} hint="PNG, JPG, atau WEBP · maks 5 MB" onError={setError} />
          <div className="flex justify-end gap-2">
            {src && (
              <button
                type="button"
                disabled={pending}
                onClick={async () => {
                  const ok = await confirm({
                    title: `Hapus ${alt.toLowerCase()}?`,
                    message: `${alt} akan dihapus dan tidak lagi tampil pada dokumen yang dicetak berikutnya.`,
                    confirmLabel: "Hapus",
                    tone: "danger",
                  });
                  if (ok) jalankan(hapus, "Gambar dihapus.");
                }}
                className={buttonClass("secondary", "sm")}
              >
                Hapus
              </button>
            )}
            <button type="submit" disabled={pending} className={buttonClass("primary", "sm")}>
              {pending ? "Memproses…" : "Unggah"}
            </button>
          </div>
        </form>
      )}
      {error && <p role="alert" className="text-xs text-red-600">{error}</p>}
    </div>
  );
}
