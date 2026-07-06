import Link from "next/link";

const TABS = [
  { seg: "", label: "Ringkasan" },
  { seg: "profil-lulusan", label: "Profil Lulusan" },
  { seg: "cpl", label: "CPL" },
  { seg: "bahan-kajian", label: "Bahan Kajian" },
  { seg: "mata-kuliah", label: "Mata Kuliah" },
];

export function KurikulumTabs({ id, active }: { id: string; active: string }) {
  return (
    <nav className="mb-6 flex flex-wrap gap-1 border-b border-border">
      {TABS.map((t) => {
        const href = t.seg ? `/kurikulum/${id}/${t.seg}` : `/kurikulum/${id}`;
        const on = t.seg === active;
        return (
          <Link
            key={t.seg || "ringkasan"}
            href={href}
            className={`-mb-px border-b-2 px-3.5 py-2 text-sm font-medium transition ${
              on
                ? "border-brand-600 text-brand-700"
                : "border-transparent text-muted hover:text-ink"
            }`}
          >
            {t.label}
          </Link>
        );
      })}
    </nav>
  );
}
