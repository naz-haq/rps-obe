import { apiGet, type Paginated, type DokumenRujukan, type BadanRujukan } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, SortableTh, Pagination, Badge, EmptyState } from "@/components/ui";
import {
  UploadDokumenButton,
  ReindexButton,
  DeleteDokumenButton,
  CreateBadanButton,
  DeleteBadanButton,
} from "./forms";

type SearchParams = Promise<{ sort?: string; dir?: string; page?: string; status?: string; jenis?: string }>;

const DEFAULT_INSTITUSI = 1;

const STATUS_TONE: Record<string, "ok" | "warn" | "danger"> = {
  indexed: "ok",
  pending: "warn",
  error: "danger",
};
const STATUS_LABEL: Record<string, string> = { indexed: "Terindeks", pending: "Menunggu", error: "Gagal" };
const JENIS_LABEL: Record<string, string> = {
  kpt: "Pedoman KPT",
  asosiasi: "Asosiasi",
  akreditasi: "Akreditasi",
  template_rps: "Template RPS",
};
const BADAN_JENIS_LABEL: Record<string, string> = {
  asosiasi: "Asosiasi",
  akreditasi: "Akreditasi",
  pemerintah: "Pemerintah",
  institusi: "Institusi",
};

export default async function DokumenRujukanPage({ searchParams }: { searchParams: SearchParams }) {
  const sp = await searchParams;
  const sort = sp.sort ?? "created_at";
  const dir = sp.dir ?? "desc";
  const page = sp.page ?? "1";

  const [docs, badanRes] = await Promise.all([
    apiGet<Paginated<DokumenRujukan>>("/dokumen-rujukan", {
      institusi_id: DEFAULT_INSTITUSI,
      sort,
      dir,
      page,
      status: sp.status,
      jenis: sp.jenis,
      per_page: 15,
    }).catch(() => null),
    apiGet<Paginated<BadanRujukan>>("/badan-rujukan", { institusi_id: DEFAULT_INSTITUSI, per_page: 100 }).catch(() => null),
  ]);

  const badanList = badanRes?.data ?? [];
  const params = { sort, dir, status: sp.status, jenis: sp.jenis };
  const basePath = "/dokumen-rujukan";

  return (
    <div>
      <PageHeader
        title="Dokumen Rujukan"
        subtitle="Unggah pedoman KPT, rujukan asosiasi, kriteria akreditasi, atau template RPS. Dokumen diindeks (RAG) untuk grounding & anti-halusinasi AI."
        actions={
          <div className="flex items-center gap-2">
            <CreateBadanButton />
            <UploadDokumenButton badanList={badanList} />
          </div>
        }
      />

      {!docs || docs.data.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada dokumen" hint="Unggah dokumen rujukan untuk mulai membangun basis pengetahuan." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <SortableTh label="Judul" column="judul" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Jenis" column="jenis" sort={sort} dir={dir} basePath={basePath} params={params} />
                <Th>Badan</Th>
                <SortableTh label="Status" column="status_indexing" sort={sort} dir={dir} basePath={basePath} params={params} />
                <SortableTh label="Potongan" column="chunk_count" sort={sort} dir={dir} basePath={basePath} params={params} className="text-right" />
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {docs.data.map((d) => (
                <tr key={d.id} className="hover:bg-gray-50">
                  <Td>
                    <p className="font-medium text-ink">{d.judul ?? d.file_asal ?? "—"}</p>
                    {d.file_asal && <p className="text-xs text-muted">{d.file_asal}</p>}
                  </Td>
                  <Td><Badge tone="neutral">{JENIS_LABEL[d.jenis] ?? d.jenis}</Badge></Td>
                  <Td className="text-muted">{d.badan_rujukan ?? "—"}</Td>
                  <Td><Badge tone={STATUS_TONE[d.status_indexing] ?? "neutral"}>{STATUS_LABEL[d.status_indexing] ?? d.status_indexing}</Badge></Td>
                  <Td className="text-right tabular-nums">{d.chunk_count ?? 0}</Td>
                  <Td>
                    <div className="flex justify-end gap-1">
                      <ReindexButton id={d.id} />
                      <DeleteDokumenButton id={d.id} judul={d.judul ?? d.file_asal ?? "dokumen"} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <Pagination meta={docs.meta} basePath={basePath} params={params} />
        </Card>
      )}

      {/* Badan Rujukan */}
      <Card className="mt-6">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="text-sm font-semibold text-ink">Badan Rujukan</h2>
          <p className="text-xs text-muted">Sumber otoritas dokumen (asosiasi, lembaga akreditasi, pemerintah, institusi).</p>
        </div>
        {badanList.length === 0 ? (
          <EmptyState title="Belum ada badan rujukan" hint="Tambahkan badan rujukan lewat tombol di atas." />
        ) : (
          <Table>
            <thead>
              <tr>
                <Th>Nama</Th>
                <Th>Jenis</Th>
                <Th>Disiplin</Th>
                <Th className="text-right">Dokumen</Th>
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {badanList.map((b) => (
                <tr key={b.id} className="hover:bg-gray-50">
                  <Td className="font-medium text-ink">{b.nama}</Td>
                  <Td><Badge tone="neutral">{BADAN_JENIS_LABEL[b.jenis] ?? b.jenis}</Badge></Td>
                  <Td className="text-muted">{b.disiplin ?? "—"}</Td>
                  <Td className="text-right tabular-nums">{b.dokumen_count ?? 0}</Td>
                  <Td>
                    <div className="flex justify-end">
                      <DeleteBadanButton id={b.id} nama={b.nama} />
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  );
}
