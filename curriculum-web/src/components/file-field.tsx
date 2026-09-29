"use client";

import { useEffect, useRef, useState } from "react";

/** Area unggah berkas baku: klik untuk memilih, hint format/ukuran, pratinjau gambar, validasi ukuran di klien. */
export function FileField({
  name,
  accept,
  maxMb,
  hint,
  onError,
}: {
  name: string;
  accept: string;
  maxMb: number;
  hint: string;
  onError?: (pesan: string | null) => void;
}) {
  const ref = useRef<HTMLInputElement>(null);
  const urlRef = useRef<string | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);

  useEffect(() => () => {
    if (urlRef.current) URL.revokeObjectURL(urlRef.current);
  }, []);

  const pilih = (f: File | null) => {
    if (urlRef.current) URL.revokeObjectURL(urlRef.current);
    urlRef.current = null;
    setPreview(null);
    if (f && f.size > maxMb * 1024 * 1024) {
      onError?.(`Ukuran berkas melebihi ${maxMb} MB.`);
      if (ref.current) ref.current.value = "";
      setFile(null);
      return;
    }
    onError?.(null);
    setFile(f);
    if (f && f.type.startsWith("image/")) {
      urlRef.current = URL.createObjectURL(f);
      setPreview(urlRef.current);
    }
  };

  return (
    <label className="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-border bg-gray-50/60 p-4 hover:border-brand-400">
      <input
        ref={ref}
        type="file"
        name={name}
        accept={accept}
        className="sr-only"
        onChange={(e) => pilih(e.target.files?.[0] ?? null)}
      />
      {preview && (
        // eslint-disable-next-line @next/next/no-img-element -- pratinjau blob lokal
        <img src={preview} alt="" className="h-14 w-14 rounded-md border border-border bg-white object-contain" />
      )}
      <span className="min-w-0 text-sm">
        {file ? (
          <>
            <span className="block truncate font-medium text-ink">{file.name}</span>
            <span className="text-xs text-muted">{(file.size / 1024).toFixed(0)} KB · klik untuk mengganti</span>
          </>
        ) : (
          <>
            <span className="block font-medium text-brand-700">Klik untuk memilih file</span>
            <span className="text-xs text-muted">{hint}</span>
          </>
        )}
      </span>
    </label>
  );
}
