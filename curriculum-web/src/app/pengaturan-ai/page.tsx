import { apiGet, type AiPengaturan } from "@/lib/api";
import { PageHeader, Card, CardBody, Badge, Table, Th, Td } from "@/components/ui";
import { setProfil } from "./actions";
import { ModelPicker } from "./model-picker";

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
  nvidia: "ok",
};

const PROFIL_HINT: Record<string, string> = {
  produksi: "Produksi memakai Claude & GPT untuk mutu tinggi saat go-live.",
  simulasi: "Simulasi memakai Gemini & DeepSeek (murah/gratis) untuk menguji alur.",
  simulasi_nvidia:
    "Simulasi NVIDIA memakai model gratis GPT-OSS di NVIDIA NIM; validator memakai Gemini Flash Lite agar lolos lintas-provider.",
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

      {/* Profil aktif — bar ringkas */}
      <Card className="mt-4 animate-fade-up">
        <CardBody>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs font-medium uppercase tracking-wide text-muted">Profil aktif</span>
              <span className="text-lg font-semibold capitalize text-ink">{cfg.profil_aktif}</span>
              <Badge tone={cfg.profil_aktif === "produksi" ? "brand" : "warn"}>
                {cfg.profil_aktif === "produksi" ? "Primer" : "Simulasi"}
              </Badge>
            </div>
            <form action={setProfil} className="flex flex-wrap items-center gap-1.5">
              {cfg.profil_tersedia.map((p) => {
                const active = p === cfg!.profil_aktif;
                return (
                  <button
                    key={p}
                    name="profil"
                    value={p}
                    disabled={active}
                    className={`inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition focus-ring ${
                      active
                        ? "cursor-default bg-brand-600 text-white"
                        : "border border-border bg-surface text-ink hover:bg-gray-50"
                    }`}
                  >
                    <span className="capitalize">{p}</span>
                  </button>
                );
              })}
            </form>
          </div>
          <p className="mt-2 text-xs text-muted">
            {PROFIL_HINT[cfg.profil_aktif] ?? "Pilih jalur model sesuai kebutuhan."}{" "}
            <span className="opacity-70">
              Default env: {cfg.default_env} · Global tersimpan: {cfg.global_tersimpan ?? "—"}
            </span>
          </p>
        </CardBody>
      </Card>

      {/* Pemilihan model manual per-tugas */}
      <Card className="mt-6 animate-fade-up">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Pemilihan Model Manual per‑Tugas</h2>
          <p className="text-xs text-muted">
            Timpa model tiap tugas dari daftar model yang API key‑nya tersedia. Kosong = ikuti profil{" "}
            <span className="font-medium capitalize">{cfg.profil_aktif}</span>. Aturan lintas‑provider
            (validator ≠ provider generate) tetap ditegakkan.
          </p>
        </div>
        <CardBody>
          <ModelPicker cfg={cfg} />
        </CardBody>
      </Card>

      {/* Rincian pemetaan per profil — dapat dilipat */}
      <details className="mt-6 rounded-2xl border border-border bg-surface">
        <summary className="cursor-pointer px-5 py-3.5 text-sm font-semibold text-ink">
          Pemetaan Model per Profil
        </summary>
        <div className="grid grid-cols-1 gap-4 p-5 pt-0 sm:grid-cols-2 lg:grid-cols-3">
          {cfg.profil_tersedia.map((profil) => {
            const map = cfg!.profiles[profil] ?? {};
            const isActive = profil === cfg!.profil_aktif;
            return (
              <div
                key={profil}
                className={`rounded-xl border ${isActive ? "border-brand-200 ring-1 ring-brand-100" : "border-border"}`}
              >
                <div className="flex items-center justify-between border-b border-border px-4 py-2.5">
                  <h3 className="text-sm font-semibold capitalize text-ink">{profil}</h3>
                  {isActive && <Badge tone="brand">aktif</Badge>}
                </div>
                <ul className="divide-y divide-border">
                  {Object.entries(map).map(([task, modelKey]) => (
                    <li key={task} className="flex items-center justify-between gap-2 px-4 py-2">
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
              </div>
            );
          })}
        </div>
      </details>

      {/* Katalog model — dapat dilipat */}
      <details className="mt-6 rounded-2xl border border-border bg-surface">
        <summary className="cursor-pointer px-5 py-3.5 text-sm font-semibold text-ink">
          Katalog Model <span className="font-normal text-muted">· harga USD / 1 juta token</span>
        </summary>
        <div className="px-1 pb-2">
          <Table>
            <thead>
              <tr>
                <Th>Model</Th>
                <Th>Provider</Th>
                <Th>Nama API</Th>
                <Th>Status</Th>
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
                    <Td>
                      {m.tersedia ? (
                        <Badge tone="ok">tersedia</Badge>
                      ) : (
                        <Badge tone="neutral">tanpa key</Badge>
                      )}
                    </Td>
                    <Td className="text-right tabular-nums">${m.pricing?.input?.toFixed(2) ?? "—"}</Td>
                    <Td className="text-right tabular-nums">${m.pricing?.output?.toFixed(2) ?? "—"}</Td>
                  </tr>
                ))}
            </tbody>
          </Table>
        </div>
      </details>
    </div>
  );
}
