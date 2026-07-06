import { apiGet, type InstitusiData } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, Badge, EmptyState } from "@/components/ui";
import { CreateInstitusiButton, EditInstitusiButton, DeleteInstitusiButton } from "./forms";

export const dynamic = "force-dynamic";

const JENIS_LABEL: Record<string, string> = { prodi: "Program Studi", fakultas: "Fakultas" };

export default async function ProdiPage() {
  const res = await apiGet<{ data: InstitusiData[] }>("/institusi").catch(() => ({ data: [] as InstitusiData[] }));
  const items = res.data;

  const fakultas = items.filter((i) => i.jenis === "fakultas");
  const fakultasOpts = fakultas.map((f) => ({ id: f.id, nama: f.nama }));

  // Kelompokkan prodi di bawah fakultas induknya (berjenjang).
  const prodiByParent = new Map<number, InstitusiData[]>();
  const orphanProdi: InstitusiData[] = [];
  for (const i of items) {
    if (i.jenis !== "prodi") continue;
    if (i.parent_id && fakultas.some((f) => f.id === i.parent_id)) {
      const list = prodiByParent.get(i.parent_id) ?? [];
      list.push(i);
      prodiByParent.set(i.parent_id, list);
    } else {
      orphanProdi.push(i);
    }
  }

  return (
    <div>
      <PageHeader
        title="Prodi & Unit"
        subtitle="Kelola data fakultas & program studi secara berjenjang. Setiap prodi terikat pada satu fakultas induk."
        actions={<CreateInstitusiButton fakultas={fakultasOpts} />}
      />

      {items.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada prodi/unit" hint="Tambahkan fakultas terlebih dahulu, lalu program studi di bawahnya." />
        </Card>
      ) : (
        <Card>
          <Table>
            <thead>
              <tr>
                <Th>Nama</Th>
                <Th>Jenis</Th>
                <Th>Kode</Th>
                <Th>Asosiasi Profesi</Th>
                <Th className="text-right">Dosen</Th>
                <Th className="text-right">Mata Kuliah</Th>
                <Th className="text-right">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {fakultas.map((f) => (
                <FakultasGroup
                  key={f.id}
                  fakultas={f}
                  prodi={prodiByParent.get(f.id) ?? []}
                  fakultasOpts={fakultasOpts}
                />
              ))}

              {orphanProdi.length > 0 && (
                <>
                  <tr className="bg-gray-50">
                    <Td colSpan={7} className="text-xs font-medium uppercase tracking-wide text-muted">
                      Tanpa fakultas
                    </Td>
                  </tr>
                  {orphanProdi.map((i) => (
                    <ProdiRow key={i.id} item={i} fakultasOpts={fakultasOpts} />
                  ))}
                </>
              )}
            </tbody>
          </Table>
        </Card>
      )}
    </div>
  );
}

type FakultasOpt = { id: number; nama: string };

function FakultasGroup({
  fakultas,
  prodi,
  fakultasOpts,
}: {
  fakultas: InstitusiData;
  prodi: InstitusiData[];
  fakultasOpts: FakultasOpt[];
}) {
  return (
    <>
      <tr className="bg-brand-50/60 hover:bg-brand-50">
        <Td>
          <p className="font-semibold text-ink">{fakultas.nama}</p>
        </Td>
        <Td>
          <Badge tone="ok">{JENIS_LABEL.fakultas}</Badge>
        </Td>
        <Td className="text-muted">{fakultas.kode ?? "—"}</Td>
        <Td className="text-muted">{fakultas.asosiasi_profesi ?? "—"}</Td>
        <Td className="text-right tabular-nums">{fakultas.dosen_count}</Td>
        <Td className="text-right tabular-nums">{fakultas.mata_kuliah_count}</Td>
        <Td>
          <div className="flex justify-end gap-1">
            <EditInstitusiButton item={fakultas} fakultas={fakultasOpts} />
            <DeleteInstitusiButton item={fakultas} />
          </div>
        </Td>
      </tr>
      {prodi.map((i) => (
        <ProdiRow key={i.id} item={i} fakultasOpts={fakultasOpts} indent />
      ))}
    </>
  );
}

function ProdiRow({
  item,
  fakultasOpts,
  indent,
}: {
  item: InstitusiData;
  fakultasOpts: FakultasOpt[];
  indent?: boolean;
}) {
  return (
    <tr className="hover:bg-gray-50">
      <Td>
        <p className={`text-ink ${indent ? "pl-5" : ""}`}>
          {indent && <span className="mr-2 text-muted">└</span>}
          {item.nama}
        </p>
      </Td>
      <Td>
        <Badge tone="neutral">{JENIS_LABEL[item.jenis] ?? item.jenis}</Badge>
      </Td>
      <Td className="text-muted">{item.kode ?? "—"}</Td>
      <Td className="text-muted">{item.asosiasi_profesi ?? "—"}</Td>
      <Td className="text-right tabular-nums">{item.dosen_count}</Td>
      <Td className="text-right tabular-nums">{item.mata_kuliah_count}</Td>
      <Td>
        <div className="flex justify-end gap-1">
          <EditInstitusiButton item={item} fakultas={fakultasOpts} />
          <DeleteInstitusiButton item={item} />
        </div>
      </Td>
    </tr>
  );
}
