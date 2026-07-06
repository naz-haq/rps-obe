import { apiGet, type Paginated, type Notifikasi } from "@/lib/api";
import { PageHeader, Card, Pagination, Badge, EmptyState } from "@/components/ui";
import { TandaiDibacaButton } from "./buttons";

type SearchParams = Promise<{ status?: string; page?: string }>;

const DEFAULT_INSTITUSI = 1;
const basePath = "/governance/notifikasi";

const STATUS_FILTER = [
  { value: "", label: "Semua" },
  { value: "unread", label: "Belum dibaca" },
  { value: "read", label: "Sudah dibaca" },
];

const JENIS_LABEL: Record<string, string> = {
  rps_disetujui: "RPS Disetujui",
  rps_revisi: "RPS Perlu Revisi",
};

function fmtWaktu(iso: string): string {
  return new Date(iso).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" });
}

export default async function NotifikasiPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const page = sp.page ?? "1";

  const res = await apiGet<Paginated<Notifikasi>>("/governance/notifikasi", {
    institusi_id: DEFAULT_INSTITUSI,
    status: sp.status,
    page,
    per_page: 20,
  }).catch(() => null);

  const params = { status: sp.status };

  return (
    <div>
      <PageHeader
        title="Notifikasi"
        subtitle="Pemberitahuan alur kerja RPS (disetujui / perlu revisi) untuk pemangku kepentingan terkait."
      />

      <div className="mb-4 flex flex-wrap gap-1.5">
        {STATUS_FILTER.map((f) => {
          const active = (sp.status ?? "") === f.value;
          const href = f.value ? `${basePath}?status=${f.value}` : basePath;
          return (
            <a
              key={f.value || "all"}
              href={href}
              className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                active ? "bg-brand-600 text-white" : "border border-border bg-surface text-muted hover:bg-gray-50"
              }`}
            >
              {f.label}
            </a>
          );
        })}
      </div>

      {!res || res.data.length === 0 ? (
        <Card>
          <EmptyState title="Tidak ada notifikasi" hint="Notifikasi muncul saat RPS disetujui atau diminta revisi." />
        </Card>
      ) : (
        <>
          <div className="space-y-2">
            {res.data.map((n) => {
              const unread = n.status === "unread";
              return (
                <Card key={n.id} className={unread ? "border-l-4 border-l-brand-500" : ""}>
                  <div className="flex items-center justify-between gap-4 p-4">
                    <div className="min-w-0">
                      <div className="mb-1 flex items-center gap-2">
                        <Badge tone={n.jenis === "rps_revisi" ? "warn" : "ok"}>
                          {JENIS_LABEL[n.jenis] ?? n.jenis}
                        </Badge>
                        {unread && <span className="text-xs font-medium text-brand-600">• Baru</span>}
                      </div>
                      <p className="truncate text-sm text-ink">{n.konten}</p>
                      <p className="mt-0.5 text-xs text-muted">{fmtWaktu(n.created_at)}</p>
                    </div>
                    {unread && <TandaiDibacaButton id={n.id} />}
                  </div>
                </Card>
              );
            })}
          </div>
          <div className="mt-4">
            <Card>
              <Pagination meta={res.meta} basePath={basePath} params={params} />
            </Card>
          </div>
        </>
      )}
    </div>
  );
}
