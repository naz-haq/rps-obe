import { apiGet, type PromptSlot } from "@/lib/api";
import Link from "next/link";
import { PageHeader, Card, CardBody, Badge } from "@/components/ui";
import { OverrideButton, EditOverrideButton, ResetOverrideButton } from "./forms";

export const metadata = { title: "Prompt AI · Curriculum Service" };

const GROUP_LABEL: Record<string, string> = {
  generator: "Generator RPS Bertahap",
  validasi: "Validasi Anti-Halusinasi",
  audit: "Audit RPS",
  konsultan: "Konsultan Kurikulum",
  lain: "Lainnya",
};

export default async function PromptsPage({ searchParams }: {
  searchParams: Promise<{ jenis_mk?: string; institusi_id?: string }>;
}) {
  const params = await searchParams;
  const jenisMk = params.jenis_mk ?? "";
  const query = new URLSearchParams();
  if (jenisMk) query.set("jenis_mk", jenisMk);
  if (params.institusi_id) query.set("institusi_id", params.institusi_id);
  const { data: slots } = await apiGet<{ data: PromptSlot[] }>(`/prompts/catalog?${query}`);
  const context = jenisMk === "praktikum" ? "Praktikum" : jenisMk === "murni" ? "Teori (murni)" : "Semua jenis MK (umum)";

  const groups = slots.reduce<Record<string, PromptSlot[]>>((acc, s) => {
    (acc[s.group] ??= []).push(s);
    return acc;
  }, {});

  const overrideCount = slots.filter((s) => s.sumber_efektif === "override").length;

  return (
    <div>
      <PageHeader
        title="Prompt AI"
        actions={
          <Badge tone={overrideCount ? "warn" : "neutral"}>
            {overrideCount ? `${overrideCount} override aktif` : "Semua default"}
          </Badge>
        }
      />

      <nav aria-label="Jenis mata kuliah" className="mb-3 flex flex-wrap gap-2">
        {[["", "Semua"], ["murni", "Teori (murni)"], ["praktikum", "Praktikum"]].map(([value, label]) => {
          const filter = new URLSearchParams(query);
          if (value) filter.set("jenis_mk", value);
          else filter.delete("jenis_mk");
          return <Link key={value} href={`/prompts?${filter}`} aria-current={jenisMk === value ? "page" : undefined}
            className={`rounded-lg border px-3 py-2 text-sm ${jenisMk === value ? "border-brand-500 bg-brand-50 text-brand-700" : "border-border text-muted"}`}>
            {label}
          </Link>;
        })}
      </nav>
      <p className="mb-6 text-xs text-muted" role="status">
        Konteks: {context} · {slots[0]?.institusi_id != null ? `Institusi #${slots[0].institusi_id}` : "Global"}.
        {jenisMk ? " Reset hanya mengubah slot dan jenis MK ini." : " Semua menampilkan konteks umum, bukan gabungan semua jenis; override khusus Teori/Praktikum tetap berlaku."}
        {" Reset memakai default kode terbaru, melewati override lama/global, tanpa menghapus riwayat."}
      </p>

      <div className="space-y-8">
        {Object.entries(groups).map(([group, items]) => (
          <section key={group}>
            <h2 className="mb-3 text-sm font-semibold text-muted">{GROUP_LABEL[group] ?? group}</h2>
            <div className="space-y-4">
              {items.map((slot) => (
                <SlotCard key={`${slot.slot}:${slot.jenis_mk ?? "all"}:${slot.institusi_id ?? "global"}`} slot={slot} />
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
  const effectiveSystem = slot.effective_system;
  const effectiveSchema = slot.effective_schema;

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
                <Badge tone="warn">Override · {ov?.institusi_id == null ? "Global" : "Institusi"} · {ov?.jenis_mk ?? "Umum"} · v{ov?.versi}</Badge>
              ) : (
                <Badge tone="ok">Default</Badge>
              )}
              {slot.selection?.use_default && <Badge tone="neutral">Default dipilih · v{slot.selection.versi}</Badge>}
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {slot.can_edit && <EditOverrideButton slot={slot} />}
            <OverrideButton slot={slot} />
            <ResetOverrideButton slot={slot} />
          </div>
        </div>

        <div>
          <p className="mb-1 text-xs font-medium text-muted">Prompt sistem efektif</p>
          <pre className="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg border border-border bg-gray-50 p-3 text-xs leading-relaxed text-ink">
            {effectiveSystem}
          </pre>
        </div>

        {effectiveSchema ? (
          <div>
            <p className="mb-1 text-xs font-medium text-muted">Skema keluaran</p>
            <pre className="overflow-auto rounded-lg border border-border bg-gray-50 p-3 font-mono text-[11px] leading-relaxed text-gray-700">
              {effectiveSchema}
            </pre>
          </div>
        ) : <p className="text-xs text-muted">Keluaran teks bebas — tanpa skema JSON.</p>}

        {isOverride && !slot.can_edit && (
          <p className="text-xs text-muted">
            Prompt diwarisi. Pilih &ldquo;Override prompt&rdquo; untuk membuat versi pada konteks ini,
            atau kembalikan ke default tanpa mengubah prompt sumber.
          </p>
        )}
      </CardBody>
    </Card>
  );
}
