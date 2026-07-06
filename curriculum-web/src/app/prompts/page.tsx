import { apiGet, type PromptSlot } from "@/lib/api";
import { PageHeader, Card, CardBody, Badge } from "@/components/ui";
import { OverrideButton, EditOverrideButton, ResetOverrideButton } from "./forms";

export const metadata = { title: "Prompt AI · Curriculum Service" };

const GROUP_LABEL: Record<string, string> = {
  generator: "Generator RPS Bertahap",
  validasi: "Validasi Anti-Halusinasi",
  lain: "Lainnya",
};

export default async function PromptsPage() {
  const { data: slots } = await apiGet<{ data: PromptSlot[] }>("/prompts/catalog");

  const groups = slots.reduce<Record<string, PromptSlot[]>>((acc, s) => {
    (acc[s.group] ??= []).push(s);
    return acc;
  }, {});

  const overrideCount = slots.filter((s) => s.sumber_efektif === "override").length;

  return (
    <div>
      <PageHeader
        title="Prompt AI"
        subtitle="Pusat kendali semua prompt sistem. Teks default aman di kode; buat override bila ingin menyesuaikan tanpa deploy."
        actions={
          <Badge tone={overrideCount ? "warn" : "neutral"}>
            {overrideCount ? `${overrideCount} override aktif` : "Semua default"}
          </Badge>
        }
      />

      <div className="space-y-8">
        {Object.entries(groups).map(([group, items]) => (
          <section key={group}>
            <h2 className="mb-3 text-sm font-semibold text-muted">{GROUP_LABEL[group] ?? group}</h2>
            <div className="space-y-4">
              {items.map((slot) => (
                <SlotCard key={slot.slot} slot={slot} />
              ))}
            </div>
          </section>
        ))}
      </div>
    </div>
  );
}

function SlotCard({ slot }: { slot: PromptSlot }) {
  const isOverride = slot.sumber_efektif === "override";
  const ov = slot.override;
  const effectiveSystem = isOverride && ov ? ov.sistem_prompt : slot.default_system;
  const effectiveSchema = isOverride && ov ? ov.skema_output : slot.default_schema;

  return (
    <Card>
      <CardBody className="space-y-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <h3 className="text-sm font-semibold text-ink">{slot.label}</h3>
              <code className="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">
                {slot.slot}
              </code>
              {isOverride ? (
                <Badge tone="warn">Override{ov?.jenis_mk ? ` · ${ov.jenis_mk}` : ""}</Badge>
              ) : (
                <Badge tone="ok">Default</Badge>
              )}
              {isOverride && ov && !ov.aktif && <Badge tone="neutral">Nonaktif</Badge>}
            </div>
          </div>
          <div className="flex items-center gap-2">
            {isOverride && ov ? (
              <>
                <EditOverrideButton slot={slot} />
                <ResetOverrideButton id={ov.id} />
              </>
            ) : (
              <OverrideButton slot={slot} />
            )}
          </div>
        </div>

        <div>
          <p className="mb-1 text-xs font-medium text-muted">Prompt sistem efektif</p>
          <pre className="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg border border-border bg-gray-50 p-3 text-xs leading-relaxed text-ink">
            {effectiveSystem}
          </pre>
        </div>

        {effectiveSchema && (
          <div>
            <p className="mb-1 text-xs font-medium text-muted">Skema keluaran</p>
            <pre className="overflow-auto rounded-lg border border-border bg-gray-50 p-3 font-mono text-[11px] leading-relaxed text-gray-700">
              {effectiveSchema}
            </pre>
          </div>
        )}

        {isOverride && (
          <p className="text-xs text-muted">
            Prompt bawaan masih tersimpan di kode — klik &ldquo;Kembalikan default&rdquo; untuk
            memulihkannya.
          </p>
        )}
      </CardBody>
    </Card>
  );
}
