import { apiGet, type Taksonomi } from "@/lib/api";
import { PageHeader, Card, CardBody, Badge, Table, Th, Td, EmptyState } from "@/components/ui";
import { CreateTaksonomiButton, EditTaksonomiButton, DeleteTaksonomiButton } from "./forms";

const GROUPS: { kerangka: string; domain: string; title: string; hint: string }[] = [
  { kerangka: "bloom_anderson", domain: "kognitif", title: "Kognitif — Bloom (Anderson)", hint: "C1–C6" },
  { kerangka: "krathwohl", domain: "afektif", title: "Afektif — Krathwohl", hint: "A1–A5" },
  { kerangka: "dave", domain: "psikomotorik", title: "Psikomotorik — Dave", hint: "P1–P5" },
  { kerangka: "simpson", domain: "psikomotorik", title: "Psikomotorik — Simpson", hint: "alternatif" },
];

const DOMAIN_TONE: Record<string, "brand" | "ok" | "warn" | "neutral"> = {
  kognitif: "brand",
  afektif: "ok",
  psikomotorik: "warn",
};

export default async function TaksonomiPage() {
  let list: Taksonomi[] = [];
  let error: string | null = null;
  try {
    const res = await apiGet<{ data: Taksonomi[] }>("/taksonomi", { institusi_id: 1, per_page: 200 });
    list = res.data;
  } catch {
    error = "Tidak dapat memuat taksonomi. Pastikan backend berjalan di :8100.";
  }

  const byKerangka = (k: string) =>
    list.filter((t) => t.kerangka === k).sort((a, b) => a.level - b.level);

  const groups = GROUPS.filter((g) => byKerangka(g.kerangka).length > 0 || g.kerangka !== "simpson");

  // Tata letak kerangka taksonomi:
  // - 3 grup atau kurang → tetap satu baris (3 kolom pada layar lebar).
  // - lebih dari 3 grup → dua baris seimbang (2 kolom → komposisi 2 dan 2).
  const colClass = groups.length > 3 ? "xl:grid-cols-2" : "xl:grid-cols-3";

  return (
    <div>
      <PageHeader
        title="Taksonomi"
        subtitle="Master level taksonomi & kata kerja operasional — acuan penulisan CPL, CPMK, dan Sub-CPMK."
        actions={<CreateTaksonomiButton />}
      />

      {error ? (
        <Card>
          <CardBody>
            <p className="text-sm text-red-600">{error}</p>
          </CardBody>
        </Card>
      ) : list.length === 0 ? (
        <EmptyState title="Belum ada taksonomi" hint="Tambahkan level taksonomi pertama atau jalankan seeder bawaan." />
      ) : (
        <div className={`grid grid-cols-1 gap-4 md:grid-cols-2 ${colClass}`}>
          {groups.map((g) => {
            const rows = byKerangka(g.kerangka);
            return (
              <Card key={g.kerangka} className="animate-fade-up">
                <div className="flex items-center justify-between border-b border-border px-4 py-2.5">
                  <h2 className="flex items-center gap-2 text-sm font-semibold text-ink">
                    {g.title}
                    <span className="text-xs font-normal text-muted">{g.hint}</span>
                  </h2>
                  <CreateTaksonomiButton defaults={{ domain: g.domain, kerangka: g.kerangka }} />
                </div>
                <Table>
                  <thead>
                    <tr>
                      <Th className="w-14">Kode</Th>
                      <Th>Level &amp; kata kerja operasional</Th>
                      <Th className="w-16 text-right">Aksi</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((t) => (
                      <tr key={t.id} className="align-top">
                        <Td>
                          <Badge tone={DOMAIN_TONE[g.domain]}>{t.kode}</Badge>
                        </Td>
                        <Td>
                          <div className="flex items-baseline gap-2">
                            <span className="text-sm font-medium text-ink">{t.nama}</span>
                            <span className="text-xs text-muted">lvl {t.level}</span>
                          </div>
                          {t.kata_kerja.length > 0 && (
                            <div className="mt-1 flex flex-wrap gap-1">
                              {t.kata_kerja.map((kk, i) => (
                                <span
                                  key={i}
                                  className="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600"
                                >
                                  {kk}
                                </span>
                              ))}
                            </div>
                          )}
                        </Td>
                        <Td className="text-right">
                          <div className="inline-flex items-center gap-0.5">
                            <EditTaksonomiButton t={t} />
                            <DeleteTaksonomiButton t={t} />
                          </div>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
