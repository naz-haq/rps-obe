import { apiGet, API_BASE_URL, type TemplateRps } from "@/lib/api";
import { PageHeader, Card, CardBody, Badge, EmptyState } from "@/components/ui";
import { UploadTemplateButton, ActivateButton, EditTemplateButton, DeleteTemplateButton } from "./forms";

export const metadata = { title: "Template RPS · Curriculum Service" };

export default async function TemplateRpsPage() {
  const { data: templates } = await apiGet<{ data: TemplateRps[] }>("/template-rps");
  const active = templates.find((t) => t.is_active);

  return (
    <div>
      <PageHeader
        title="Template RPS"
        subtitle="Unggah format/template dokumen RPS agar hasil cetak seragam di seluruh program studi. Template aktif dipakai sebagai acuan."
        actions={<UploadTemplateButton />}
      />

      {active && (
        <Card className="mb-4 border-brand-200 bg-brand-50">
          <CardBody className="flex flex-wrap items-center justify-between gap-3">
            <div className="text-sm">
              <span className="font-medium text-brand-700">Template aktif:</span>{" "}
              <span className="text-ink">{active.nama}</span>{" "}
              <span className="text-muted">({active.format?.toUpperCase()})</span>
            </div>
            <a
              href={`${API_BASE_URL}/template-rps/${active.id}/download`}
              className="text-sm font-medium text-brand-700 underline"
            >
              Unduh
            </a>
          </CardBody>
        </Card>
      )}

      {templates.length === 0 ? (
        <EmptyState
          title="Belum ada template"
          hint="Unggah berkas template pertama untuk menyeragamkan format cetak RPS."
        />
      ) : (
        <div className="space-y-3">
          {templates.map((t) => (
            <Card key={t.id}>
              <CardBody className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium text-ink">{t.nama}</span>
                    {t.is_active ? (
                      <Badge tone="ok">Aktif</Badge>
                    ) : (
                      <Badge tone="neutral">Nonaktif</Badge>
                    )}
                    {t.format && <Badge tone="neutral">{t.format.toUpperCase()}</Badge>}
                  </div>
                  {t.keterangan && <p className="text-sm text-muted">{t.keterangan}</p>}
                  {t.berkas_nama_asli && (
                    <p className="text-xs text-muted">Berkas: {t.berkas_nama_asli}</p>
                  )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <a
                    href={`${API_BASE_URL}/template-rps/${t.id}/download`}
                    className="text-sm font-medium text-brand-700 underline"
                  >
                    Unduh
                  </a>
                  {!t.is_active && <ActivateButton template={t} />}
                  <EditTemplateButton template={t} />
                  <DeleteTemplateButton template={t} />
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
