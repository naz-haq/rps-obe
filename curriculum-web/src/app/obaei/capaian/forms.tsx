"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import { useToast } from "@/components/toast";
import type { CapaianMahasiswa } from "@/lib/api";
import { simpanCapaian, hapusCapaian } from "./actions";

export function TambahCapaian() {
  return (
    <Modal trigger="+ Data Capaian" title="Tambah Data Capaian Mahasiswa" triggerVariant="primary">
      {(close) => <CapaianForm close={close} />}
    </Modal>
  );
}

export function EditCapaian({ capaian }: { capaian: CapaianMahasiswa }) {
  return (
    <Modal trigger="Ubah" title="Ubah Data Capaian" triggerVariant="ghost" triggerSize="sm">
      {(close) => <CapaianForm capaian={capaian} close={close} />}
    </Modal>
  );
}

function CapaianForm({ capaian, close }: { capaian?: CapaianMahasiswa; close: () => void }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <form
      action={async (fd) => {
        setPending(true);
        setError(null);
        const r = await simpanCapaian({
          id: capaian?.id,
          kode_mk: String(fd.get("kode_mk") ?? "").trim(),
          cpmk_id: fd.get("cpmk_id") ? Number(fd.get("cpmk_id")) : null,
          sub_cpmk_id: fd.get("sub_cpmk_id") ? Number(fd.get("sub_cpmk_id")) : null,
          angkatan: String(fd.get("angkatan") ?? ""),
          jumlah_mahasiswa: fd.get("jumlah_mahasiswa") ? Number(fd.get("jumlah_mahasiswa")) : null,
          nilai_rata_rata: fd.get("nilai_rata_rata") ? Number(fd.get("nilai_rata_rata")) : null,
          persentase_capaian_minimal: fd.get("persentase_capaian_minimal")
            ? Number(fd.get("persentase_capaian_minimal"))
            : null,
        });
        setPending(false);
        if (r.ok) {
          toast({ type: "success", message: "Data capaian tersimpan." });
          router.refresh();
          close();
        } else {
          setError(r.message ?? "Gagal menyimpan data capaian.");
          toast({ type: "error", message: r.message ?? "Gagal menyimpan data capaian." });
        }
      }}
      className="space-y-3"
    >
      <div className="grid grid-cols-2 gap-3">
        <Field label="Kode MK" name="kode_mk" defaultValue={capaian?.kode_mk ?? ""} placeholder="mis. FAR101" required />
        <Field label="Angkatan" name="angkatan" defaultValue={capaian?.angkatan ?? ""} placeholder="mis. 2024" />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field
          label="CPMK ID"
          name="cpmk_id"
          type="number"
          defaultValue={capaian?.cpmk_id ?? ""}
          hint="ID CPMK yang diukur (opsional)"
        />
        <Field
          label="Sub-CPMK ID"
          name="sub_cpmk_id"
          type="number"
          defaultValue={capaian?.sub_cpmk_id ?? ""}
          hint="ID Sub-CPMK (opsional)"
        />
      </div>
      <div className="grid grid-cols-3 gap-3">
        <Field
          label="Jumlah Mhs"
          name="jumlah_mahasiswa"
          type="number"
          defaultValue={capaian?.jumlah_mahasiswa ?? ""}
          placeholder="mis. 40"
        />
        <Field
          label="Nilai Rata-rata"
          name="nilai_rata_rata"
          type="number"
          defaultValue={capaian?.nilai_rata_rata ?? ""}
          placeholder="mis. 78"
        />
        <Field
          label="% Capaian"
          name="persentase_capaian_minimal"
          type="number"
          defaultValue={capaian?.persentase_capaian_minimal ?? ""}
          placeholder="mis. 80"
          hint="% mhs ≥ ambang"
        />
      </div>
      {error && <p className="text-xs text-red-600">{error}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" disabled={pending} className={buttonClass("primary")}>
          {pending ? "Menyimpan…" : "Simpan"}
        </button>
      </div>
    </form>
  );
}

export function HapusCapaian({ id }: { id: number }) {
  const router = useRouter();
  const toast = useToast();
  const [pending, setPending] = useState(false);
  return (
    <button
      type="button"
      disabled={pending}
      className={buttonClass("danger", "sm")}
      onClick={async () => {
        if (!confirm("Hapus data capaian ini?")) return;
        setPending(true);
        const r = await hapusCapaian(id);
        setPending(false);
        if (r.ok) {
          toast({ type: "success", message: "Data capaian dihapus." });
          router.refresh();
        } else {
          toast({ type: "error", message: r.message ?? "Gagal menghapus data capaian." });
        }
      }}
    >
      Hapus
    </button>
  );
}
