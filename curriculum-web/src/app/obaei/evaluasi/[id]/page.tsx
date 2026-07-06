import Link from "next/link";
import { notFound } from "next/navigation";
import { apiGet, type Single, type EvaluasiCpl } from "@/lib/api";
import { PageHeader, Card, CardBody, Badge, EmptyState } from "@/components/ui";
import {
  AnalisisAiButton,
  FinalisasiButton,
  EditRingkasan,
  TambahTindakLanjut,
  EditTindakLanjut,
  HapusTindakLanjut,
} from "./forms";

type Params = Promise<{ id: string }>;

const PRIORITAS_TONE: Record<string, "danger" | "warn" | "neutral"> = {
  tinggi: "danger",
  sedang: "warn",
  rendah: "neutral",
};

export default async function EvaluasiDetailPage({ params }: { params: Params }) {
  const { id } = await params;
  const evaluasiId = Number(id);

  const res = await apiGet<Single<EvaluasiCpl>>(`/evaluasi-cpl/${evaluasiId}`).catch(() => null);
  const e = res?.data;
  if (!e) notFound();

  const isFinal = e.status === "final";
  const tindakLanjut = e.tindak_lanjut ?? [];

  return (
    <div>
      <PageHeader
        title={`Evaluasi ${e.cpl?.kode ?? `CPL #${e.cpl_id}`}`}
        subtitle={e.cpl?.deskripsi ?? undefined}
        actions={
          <div className="flex items-center gap-2">
            <Badge tone={isFinal ? "ok" : "warn"}>{isFinal ? "Final" : "Draft"}</Badge>
            {!isFinal && <AnalisisAiButton id={e.id} />}
            {!isFinal && <FinalisasiButton id={e.id} />}
            <Link href="/obaei/evaluasi" className="text-sm text-brand-700 hover:underline">
              ← Semua evaluasi
            </Link>
          </div>
        }
      />

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card>
            <CardBody>
              <div className="mb-3 flex items-center justify-between">
                <div>
                  <h3 className="text-sm font-semibold text-ink">Ringkasan Naratif</h3>
                  {e.periode && <p className="text-xs text-muted">Periode: {e.periode}</p>}
                </div>
                {!isFinal && <EditRingkasan id={e.id} periode={e.periode} ringkasan={e.ringkasan_naratif} />}
              </div>
              {e.ringkasan_naratif ? (
                <p className="whitespace-pre-wrap text-sm leading-relaxed text-ink">{e.ringkasan_naratif}</p>
              ) : (
                <p className="text-sm text-muted">
                  Belum ada ringkasan. Gunakan tombol “✨ Analisis AI” untuk menyusun narasi evaluasi otomatis
                  berdasarkan data ketercapaian, atau tulis manual lewat “Ubah Ringkasan”.
                </p>
              )}
            </CardBody>
          </Card>
        </div>

        <div>
          <Card>
            <CardBody>
              <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-ink">Tindak Lanjut</h3>
                {!isFinal && <TambahTindakLanjut evaluasiId={e.id} />}
              </div>
              {tindakLanjut.length === 0 ? (
                <EmptyState title="Belum ada tindak lanjut" hint="Tambahkan rencana perbaikan konkret." />
              ) : (
                <ul className="space-y-3">
                  {tindakLanjut.map((t) => (
                    <li key={t.id} className="rounded-lg border border-border bg-gray-50 p-3">
                      <div className="mb-1 flex items-center justify-between gap-2">
                        {t.prioritas ? (
                          <Badge tone={PRIORITAS_TONE[t.prioritas] ?? "neutral"}>
                            Prioritas {t.prioritas}
                          </Badge>
                        ) : (
                          <span className="text-xs text-muted">Tanpa prioritas</span>
                        )}
                        {t.status && <span className="text-xs text-muted">{t.status}</span>}
                      </div>
                      <p className="whitespace-pre-wrap text-sm text-ink">{t.catatan}</p>
                      {t.sub_cpmk && <p className="mt-1 text-xs text-muted">Sub-CPMK: {t.sub_cpmk}</p>}
                      {!isFinal && (
                        <div className="mt-2 flex justify-end gap-1.5">
                          <EditTindakLanjut evaluasiId={e.id} item={t} />
                          <HapusTindakLanjut id={t.id} evaluasiId={e.id} />
                        </div>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </CardBody>
          </Card>
        </div>
      </div>
    </div>
  );
}
