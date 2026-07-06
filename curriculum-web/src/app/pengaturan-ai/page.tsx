import { apiGet, type AiPengaturan } from "@/lib/api";
import { PageHeader, Card, CardBody, Badge, Table, Th, Td } from "@/components/ui";
import { setProfil } from "./actions";

const TASK_LABEL: Record<string, string> = {
  generate: "Generate RPS",
  judge: "Judge / QA",
  validator: "Validator anti-halusinasi",
  asistif: "Asistif inline",
  ekstraksi: "Ekstraksi / klasifikasi",
  konversasional: "Konversasional",
  eskalasi: "Eskalasi",
};

const PROVIDER_TONE: Record<string, "brand" | "ok" | "warn" | "neutral"> = {
  anthropic: "brand",
  openai: "ok",
  gemini: "warn",
  deepseek: "neutral",
};

export default async function PengaturanAiPage() {
  let cfg: AiPengaturan | null = null;
  let error: string | null = null;
  try {
    const res = await apiGet<{ data: AiPengaturan }>("/ai/pengaturan");
    cfg = res.data;
  } catch {
    error = "Tidak dapat memuat konfigurasi AI. Pastikan backend berjalan di :8100.";
  }

  if (error || !cfg) {
    return (
      <div>
        <PageHeader title="Konfigurasi AI" />
        <Card>
          <CardBody>
            <p className="text-sm text-red-600">{error}</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  const providerOf = (key: string) => cfg!.models.find((m) => m.key === key)?.provider ?? "?";

  return (
    <div>
      <PageHeader
        title="Konfigurasi AI"
        subtitle="Pilih jalur model aktif. Peralihan simulasi ↔ produksi langsung berlaku tanpa mengubah kode."
      />

      {/* Kartu profil aktif + switch */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="animate-fade-up lg:col-span-1">
          <CardBody>
            <p className="text-xs font-medium uppercase tracking-wide text-muted">Profil aktif</p>
            <div className="mt-2 flex items-center gap-2">
              <span className="text-2xl font-semibold capitalize text-ink">{cfg.profil_aktif}</span>
              <Badge tone={cfg.profil_aktif === "produksi" ? "brand" : "warn"}>
                {cfg.profil_aktif === "produksi" ? "Primer" : "Simulasi"}
              </Badge>
            </div>
            <dl className="mt-4 space-y-1.5 text-sm">
              <Row label="Default (env)" value={cfg.default_env} />
              <Row label="Global tersimpan" value={cfg.global_tersimpan ?? "—"} />
            </dl>

            <form action={setProfil} className="mt-5 space-y-2">
              <p className="text-xs font-medium text-muted">Ganti profil global</p>
              <div className="flex flex-wrap gap-2">
                {cfg.profil_tersedia.map((p) => {
                  const active = p === cfg!.profil_aktif;
                  return (
                    <button
                      key={p}
                      name="profil"
                      value={p}
                      disabled={active}
                      className={`inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-medium transition focus-ring ${
                        active
                          ? "cursor-default bg-brand-600 text-white"
                          : "border border-border bg-surface text-ink hover:bg-gray-50"
                      }`}
                    >
                      <span className="capitalize">{p}</span>
                      {active && <span className="text-[11px] opacity-80">aktif</span>}
                    </button>
                  );
                })}
              </div>
              <p className="pt-1 text-xs text-muted">
                {cfg.profil_aktif === "simulasi"
                  ? "Simulasi memakai Gemini & DeepSeek (murah/gratis) untuk menguji alur."
                  : "Produksi memakai Claude & GPT untuk mutu tinggi saat go-live."}
              </p>
            </form>
          </CardBody>
        </Card>

        {/* Pemetaan model per-tugas untuk tiap profil */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-2">
          {cfg.profil_tersedia.map((profil) => {
            const map = cfg!.profiles[profil] ?? {};
            const isActive = profil === cfg!.profil_aktif;
            return (
              <Card
                key={profil}
                className={`animate-fade-up ${isActive ? "ring-2 ring-brand-200" : ""}`}
              >
                <div className="flex items-center justify-between border-b border-border px-5 py-3">
                  <h3 className="text-sm font-semibold capitalize text-ink">{profil}</h3>
                  {isActive && <Badge tone="brand">aktif</Badge>}
                </div>
                <ul className="divide-y divide-border">
                  {Object.entries(map).map(([task, modelKey]) => (
                    <li key={task} className="flex items-center justify-between gap-2 px-5 py-2.5">
                      <span className="text-xs text-muted">{TASK_LABEL[task] ?? task}</span>
                      <span className="flex items-center gap-1.5">
                        <Badge tone={PROVIDER_TONE[providerOf(modelKey)] ?? "neutral"}>
                          {providerOf(modelKey)}
                        </Badge>
                        <code className="text-xs text-ink">{modelKey}</code>
                      </span>
                    </li>
                  ))}
                </ul>
              </Card>
            );
          })}
        </div>
      </div>

      {/* Katalog model */}
      <Card className="mt-6 animate-fade-up">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Katalog Model</h2>
          <p className="text-xs text-muted">Harga USD per 1 juta token (dapat ditimpa via .env).</p>
        </div>
        <Table>
          <thead>
            <tr>
              <Th>Model</Th>
              <Th>Provider</Th>
              <Th>Nama API</Th>
              <Th className="text-right">Input</Th>
              <Th className="text-right">Output</Th>
            </tr>
          </thead>
          <tbody>
            {cfg.models
              .filter((m) => m.provider !== "mock")
              .map((m) => (
                <tr key={m.key}>
                  <Td><code className="text-xs">{m.key}</code></Td>
                  <Td><Badge tone={PROVIDER_TONE[m.provider] ?? "neutral"}>{m.provider}</Badge></Td>
                  <Td className="text-muted">{m.model}</Td>
                  <Td className="text-right tabular-nums">${m.pricing?.input?.toFixed(2) ?? "—"}</Td>
                  <Td className="text-right tabular-nums">${m.pricing?.output?.toFixed(2) ?? "—"}</Td>
                </tr>
              ))}
          </tbody>
        </Table>
      </Card>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-muted">{label}</dt>
      <dd className="font-medium capitalize text-ink">{value}</dd>
    </div>
  );
}
