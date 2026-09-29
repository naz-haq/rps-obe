import { apiGet, BACKEND_PROXY, type InstitusiData } from "@/lib/api";
import { getCurrentUser, can } from "@/lib/auth";
import { PageHeader, Card, CardBody, Badge, EmptyState } from "@/components/ui";
import { GambarUnggah } from "@/components/gambar-unggah";
import { unggahLogo, hapusLogo } from "./actions";

const JENIS: Record<string, string> = { universitas: "Universitas", fakultas: "Fakultas", prodi: "Prodi" };

export default async function PengaturanDokumenPage() {
  const user = await getCurrentUser();
  const units = await apiGet<{ data: InstitusiData[] }>("/institusi").then((r) => r.data).catch(() => [] as InstitusiData[]);
  const bolehUbah = can(user, "prodi.manage");

  return (
    <div>
      <PageHeader title="Logo & Kop Dokumen" />
      <p className="mb-4 text-xs text-muted">
        Logo tampil di kop RPS (PDF &amp; Word). Unit tanpa logo memakai logo induknya: prodi → fakultas → universitas.
      </p>
      {units.length === 0 ? (
        <EmptyState title="Belum ada unit" hint="Tambahkan universitas/fakultas/prodi di menu Prodi & Unit." />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {units.map((u) => (
            <Card key={u.id}>
              <CardBody className="space-y-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-ink">{u.nama}</p>
                    {u.parent_nama && <p className="truncate text-xs text-muted">{u.parent_nama}</p>}
                  </div>
                  <Badge tone="neutral">{JENIS[u.jenis] ?? u.jenis}</Badge>
                </div>
                <GambarUnggah
                  src={u.logo_versi ? `${BACKEND_PROXY}/institusi/${u.id}/logo?v=${u.logo_versi}` : null}
                  alt="Logo"
                  kosong={u.parent_id ? "Memakai logo induk" : "Belum ada logo"}
                  unggah={unggahLogo.bind(null, u.id)}
                  hapus={hapusLogo.bind(null, u.id)}
                  bolehUbah={bolehUbah}
                />
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
