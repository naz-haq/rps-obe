import Link from "next/link";
import { apiGet, type Paginated, type GenerateSession, type MataKuliah } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import { StartSessionButton } from "./start-button";
import { DeleteSessionButton } from "./delete-button";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string }>;

const STATUS_TONE: Record<string, "ok" | "neutral" | "warn" | "brand"> = {
  committed: "ok",
  selesai: "brand",
  berjalan: "warn",
  draf: "neutral",
};

export default async function GeneratorPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  let list: Paginated<GenerateSession> | null = null;
  let mataKuliah: MataKuliah[] = [];
  let error: string | null = null;
  try {
    const [sesi, mk] = await Promise.all([
      apiGet<Paginated<GenerateSession>>("/generate-sessions", { sort, dir, page, status: sp.status, per_page: 15 }),
      apiGet<Paginated<MataKuliah>>("/mata-kuliah", { per_page: 200, sort: "kode_mk", dir: "asc" }),
    ]);
    list = sesi;
    mataKuliah = mk.data;
  } catch {
    error = "Tidak dapat memuat sesi generator. Pastikan backend berjalan di :8100.";
  }

  const params = { sort, dir, status: sp.status };

  return (
    <div>
      <PageHeader
        title="Generator RPS"
        subtitle="Susun RPS OBE bertahap: CPMK → Sub-CPMK → Rencana Mingguan → Penilaian, dengan bantuan AI."
        actions={<StartSessionButton mataKuliah={mataKuliah} />}
      />

      {error ? (
        <Card>
          <div className="p-5 text-sm text-red-600">{error}</div>
        </Card>
      ) : !list || list.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada sesi" hint="Mulai sesi baru untuk menyusun RPS sebuah mata kuliah." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>Mata Kuliah</Th>
                <SortableTh label="Tahap" column="tahap" sort={sort} dir={dir} basePath="/generator" params={params} />
                <SortableTh label="Status" column="status" sort={sort} dir={dir} basePath="/generator" params={params} />
                <Th>Sumber</Th>
                <SortableTh label="Diperbarui" column="updated_at" sort={sort} dir={dir} basePath="/generator" params={params} />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {list.data.map((s) => (
                <tr key={s.id} className="hover:bg-gray-50">
                  <Td>
                    <p className="font-medium text-ink">{s.kode_mk ?? `MK #${s.mk_id}`}</p>
                    <p className="text-xs text-muted">{s.nama_mk ?? ""}</p>
                  </Td>
                  <Td><Badge tone="neutral">{s.tahap}</Badge></Td>
                  <Td><Badge tone={STATUS_TONE[s.status] ?? "neutral"}>{s.status}</Badge></Td>
                  <Td className="text-muted">{s.sumber}</Td>
                  <Td className="text-muted">
                    {s.updated_at ? new Date(s.updated_at).toLocaleDateString("id-ID") : "—"}
                  </Td>
                  <Td className="text-right">
                    <div className="flex items-center justify-end gap-3">
                      <Link href={`/generator/${s.id}`} className="text-sm font-medium text-brand-700 hover:underline">
                        Buka →
                      </Link>
                      <DeleteSessionButton sesi={s} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={list.meta} basePath="/generator" params={params} />
        </Card>
      )}
    </div>
  );
}
