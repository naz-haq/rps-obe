import { apiGet, type Paginated, type AuditLog } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; entity?: string; q?: string }>;

const DEFAULT_INSTITUSI = 1;
const basePath = "/governance/audit";

const ENTITY_FILTER = [
  { value: "", label: "Semua entitas" },
  { value: "RpsVersion", label: "RPS" },
  { value: "EvaluasiCpl", label: "Evaluasi CPL" },
];

const ACTION_TONE: Record<string, "ok" | "warn" | "danger" | "brand" | "neutral"> = {
  "rps.setujui": "ok",
  "rps.ajukan": "brand",
  "rps.revisi": "warn",
  "rps.tarik": "neutral",
  "obaei.finalisasi": "ok",
};

function fmtWaktu(iso: string): string {
  return new Date(iso).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" });
}

export default async function AuditLogPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  const res = await apiGet<Paginated<AuditLog>>("/governance/audit-log", {
    institusi_id: DEFAULT_INSTITUSI,
    entity: sp.entity,
    q: sp.q,
    sort,
    dir,
    page,
    per_page: 20,
  }).catch(() => null);

  const params = { sort, dir, entity: sp.entity, q: sp.q };

  return (
    <div>
      <PageHeader
        title="Audit Log"
        subtitle="Jejak perubahan penting untuk kebutuhan akreditasi — siapa mengubah apa dan kapan (persetujuan RPS, finalisasi evaluasi CPL, dsb)."
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="flex flex-wrap gap-1.5">
          {ENTITY_FILTER.map((f) => {
            const active = (sp.entity ?? "") === f.value;
            const qs = new URLSearchParams();
            if (f.value) qs.set("entity", f.value);
            if (sp.q) qs.set("q", sp.q);
            return (
              <a
                key={f.value || "all"}
                href={`${basePath}${qs.toString() ? `?${qs.toString()}` : ""}`}
                className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                  active ? "bg-brand-600 text-white" : "border border-border bg-surface text-muted hover:bg-gray-50"
                }`}
              >
                {f.label}
              </a>
            );
          })}
        </div>
        <form className="ml-auto">
          {sp.entity && <input type="hidden" name="entity" value={sp.entity} />}
          <input
            name="q"
            defaultValue={sp.q ?? ""}
            placeholder="Cari aksi/entitas…"
            className="w-56 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-ink outline-none focus-ring placeholder:text-gray-400"
          />
        </form>
      </div>

      {!res || res.data.length === 0 ? (
        <Card>
          <EmptyState
            title="Belum ada jejak audit"
            hint="Jejak akan tercatat otomatis saat terjadi persetujuan RPS atau finalisasi evaluasi CPL."
          />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Waktu" column="created_at" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Aksi" column="action" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Entitas" column="entity" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th>Pelaku</Th>
                <Th>Detail</Th>
              </tr>
            </thead>
            <tbody>
              {res.data.map((a) => (
                <tr key={a.id} className="align-top hover:bg-gray-50">
                  <Td className="whitespace-nowrap text-xs text-muted">{fmtWaktu(a.created_at)}</Td>
                  <Td>
                    <Badge tone={ACTION_TONE[a.action] ?? "neutral"}>{a.action}</Badge>
                  </Td>
                  <Td className="text-xs text-muted">
                    {a.entity ?? "—"}
                    {a.entity_id ? <span className="text-gray-400"> #{a.entity_id}</span> : null}
                  </Td>
                  <Td className="text-sm text-ink">{a.actor_nama ?? <span className="text-muted">Sistem</span>}</Td>
                  <Td className="max-w-md text-xs text-muted">
                    {a.meta
                      ? Object.entries(a.meta)
                          .filter(([k, v]) => k !== "actor_nama" && v !== null && v !== "")
                          .map(([k, v]) => `${k}: ${String(v)}`)
                          .join(" · ") || "—"
                      : "—"}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={res.meta} basePath={basePath} params={params} />
        </Card>
      )}
    </div>
  );
}
