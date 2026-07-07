import { apiGet, type InstitusiData } from "@/lib/api";
import { PageHeader, Card, Table, Th, Td, Badge, EmptyState } from "@/components/ui";
import { CreateInstitusiButton, EditInstitusiButton, DeleteInstitusiButton } from "./forms";

export const dynamic = "force-dynamic";

const JENIS_LABEL: Record<string, string> = {
  universitas: "Universitas",
  fakultas: "Fakultas",
  prodi: "Program Studi",
};

type UnitOpt = { id: number; nama: string };

export default async function ProdiPage() {
  const res = await apiGet<{ data: InstitusiData[] }>("/institusi").catch(() => ({ data: [] as InstitusiData[] }));
  const items = res.data;

  const universitas = items.filter((i) => i.jenis === "universitas");
  const fakultas = items.filter((i) => i.jenis === "fakultas");
  const fakultasOpts: UnitOpt[] = fakultas.map((f) => ({ id: f.id, nama: f.nama }));
  const universitasOpts: UnitOpt[] = universitas.map((u) => ({ id: u.id, nama: u.nama }));

  // Fakultas dikelompokkan di bawah universitas induknya.
  const fakultasByParent = new Map<number, InstitusiData[]>();
  const orphanFakultas: InstitusiData[] = [];
  for (const f of fakultas) {
    if (f.parent_id && universitas.some((u) => u.id === f.parent_id)) {
      const list = fakultasByParent.get(f.parent_id) ?? [];
      list.push(f);
      fakultasByParent.set(f.parent_id, list);
    } else {
      orphanFakultas.push(f);
    }
  }

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

  const opts = { fakultasOpts, universitasOpts };

  return (
    <div>
      <PageHeader
        title="Prodi & Unit"
        subtitle="Kelola jenjang Universitas -> Fakultas -> Program Studi. Nama-nama ini dipakai pada kop dokumen RPS."
        actions={<CreateInstitusiButton fakultas={fakultasOpts} universitas={universitasOpts} />}
      />

      {items.length === 0 ? (
        <Card>
          <EmptyState title="Belum ada prodi/unit" hint="Tambahkan universitas terlebih dahulu, lalu fakultas dan program studi di bawahnya." />
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
              {universitas.map((u) => (
                <UniversitasGroup
                  key={u.id}
                  universitas={u}
                  fakultas={fakultasByParent.get(u.id) ?? []}
                  prodiByParent={prodiByParent}
                  {...opts}
                />
              ))}

              {orphanFakultas.length > 0 && (
                <>
                  <tr className="bg-gray-50">
                    <Td colSpan={7} className="text-xs font-medium uppercase tracking-wide text-muted">
                      Tanpa universitas
                    </Td>
                  </tr>
                  {orphanFakultas.map((f) => (
                    <FakultasGroup
                      key={f.id}
                      fakultas={f}
                      prodi={prodiByParent.get(f.id) ?? []}
                      {...opts}
                    />
                  ))}
                </>
              )}

              {orphanProdi.length > 0 && (
                <>
                  <tr className="bg-gray-50">
                    <Td colSpan={7} className="text-xs font-medium uppercase tracking-wide text-muted">
                      Tanpa fakultas
                    </Td>
                  </tr>
                  {orphanProdi.map((i) => (
                    <ProdiRow key={i.id} item={i} {...opts} />
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

type Opts = { fakultasOpts: UnitOpt[]; universitasOpts: UnitOpt[] };

function UniversitasGroup({
  universitas,
  fakultas,
  prodiByParent,
  fakultasOpts,
  universitasOpts,
}: {
  universitas: InstitusiData;
  fakultas: InstitusiData[];
  prodiByParent: Map<number, InstitusiData[]>;
} & Opts) {
  const opts = { fakultasOpts, universitasOpts };
  return (
    <>
      <tr className="bg-brand-100/60 hover:bg-brand-100">
        <Td>
          <p className="font-bold text-ink">{universitas.nama}</p>
        </Td>
        <Td>
          <Badge tone="brand">{JENIS_LABEL.universitas}</Badge>
        </Td>
        <Td className="text-muted">{universitas.kode ?? "—"}</Td>
        <Td className="text-muted">{universitas.asosiasi_profesi ?? "—"}</Td>
        <Td className="text-right tabular-nums">{universitas.dosen_count}</Td>
        <Td className="text-right tabular-nums">{universitas.mata_kuliah_count}</Td>
        <Td>
          <div className="flex justify-end gap-1">
            <EditInstitusiButton item={universitas} fakultas={fakultasOpts} universitas={universitasOpts} />
            <DeleteInstitusiButton item={universitas} />
          </div>
        </Td>
      </tr>
      {fakultas.map((f) => (
        <FakultasGroup key={f.id} fakultas={f} prodi={prodiByParent.get(f.id) ?? []} indent {...opts} />
      ))}
    </>
  );
}

function FakultasGroup({
  fakultas,
  prodi,
  indent,
  fakultasOpts,
  universitasOpts,
}: {
  fakultas: InstitusiData;
  prodi: InstitusiData[];
  indent?: boolean;
} & Opts) {
  const opts = { fakultasOpts, universitasOpts };
  return (
    <>
      <tr className="bg-brand-50/60 hover:bg-brand-50">
        <Td>
          <p className={`font-semibold text-ink ${indent ? "pl-5" : ""}`}>
            {indent && <span className="mr-2 text-muted">└</span>}
            {fakultas.nama}
          </p>
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
            <EditInstitusiButton item={fakultas} fakultas={fakultasOpts} universitas={universitasOpts} />
            <DeleteInstitusiButton item={fakultas} />
          </div>
        </Td>
      </tr>
      {prodi.map((i) => (
        <ProdiRow key={i.id} item={i} indent={indent ? "deep" : true} {...opts} />
      ))}
    </>
  );
}

function ProdiRow({
  item,
  indent,
  fakultasOpts,
  universitasOpts,
}: {
  item: InstitusiData;
  indent?: boolean | "deep";
} & Opts) {
  const pad = indent === "deep" ? "pl-10" : indent ? "pl-5" : "";
  return (
    <tr className="hover:bg-gray-50">
      <Td>
        <p className={`text-ink ${pad}`}>
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
          <EditInstitusiButton item={item} fakultas={fakultasOpts} universitas={universitasOpts} />
          <DeleteInstitusiButton item={item} />
        </div>
      </Td>
    </tr>
  );
}
