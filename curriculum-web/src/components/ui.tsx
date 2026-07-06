import Link from "next/link";
import type { ReactNode } from "react";

// ---- PageHeader ----
export function PageHeader({
  title,
  subtitle,
  actions,
}: {
  title: string;
  subtitle?: string;
  actions?: ReactNode;
}) {
  return (
    <header className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-ink">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-muted">{subtitle}</p>}
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </header>
  );
}

// ---- Card ----
export function Card({
  children,
  className = "",
  as: Tag = "div",
}: {
  children: ReactNode;
  className?: string;
  as?: "div" | "section";
}) {
  return (
    <Tag
      className={`rounded-xl border border-border bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)] ${className}`}
    >
      {children}
    </Tag>
  );
}

export function CardBody({ children, className = "" }: { children: ReactNode; className?: string }) {
  return <div className={`p-5 ${className}`}>{children}</div>;
}

// ---- Badge ----
const badgeTones: Record<string, string> = {
  neutral: "bg-gray-100 text-gray-700",
  brand: "bg-brand-50 text-brand-700",
  ok: "bg-emerald-50 text-emerald-700",
  warn: "bg-amber-50 text-amber-700",
  danger: "bg-red-50 text-red-700",
};

export function Badge({
  children,
  tone = "neutral",
}: {
  children: ReactNode;
  tone?: keyof typeof badgeTones;
}) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${badgeTones[tone]}`}
    >
      {children}
    </span>
  );
}

// ---- Button & Link-button ----
const btnBase =
  "inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg text-sm font-medium transition focus-ring disabled:cursor-not-allowed disabled:opacity-50 disabled:pointer-events-none";
const btnSizes = { sm: "h-8 px-3", md: "h-9 px-4" };
const btnVariants = {
  primary: "bg-brand-600 text-white hover:bg-brand-700 shadow-sm",
  secondary: "border border-border bg-surface text-ink hover:bg-gray-50",
  ghost: "text-brand-700 hover:bg-brand-50",
  danger: "border border-red-200 bg-white text-red-600 hover:bg-red-50",
};

export function buttonClass(
  variant: keyof typeof btnVariants = "primary",
  size: keyof typeof btnSizes = "md",
) {
  return `${btnBase} ${btnSizes[size]} ${btnVariants[variant]}`;
}

// Indikator loading kecil untuk tombol/aksi yang sedang berjalan.
export function Spinner({ className = "" }: { className?: string }) {
  return (
    <svg
      className={`animate-spin ${className}`}
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="3" opacity="0.25" />
      <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
    </svg>
  );
}

export function LinkButton({
  href,
  children,
  variant = "primary",
  size = "md",
}: {
  href: string;
  children: ReactNode;
  variant?: keyof typeof btnVariants;
  size?: keyof typeof btnSizes;
}) {
  return (
    <Link href={href} className={buttonClass(variant, size)}>
      {children}
    </Link>
  );
}

// ---- Table primitives ----
export function Table({ children, bordered = false }: { children: ReactNode; bordered?: boolean }) {
  return (
    <div className={`overflow-x-auto ${bordered ? "rounded-lg border border-border" : ""}`}>
      <table
        className={`w-full text-sm ${
          bordered
            ? "[&_td]:border-r [&_td]:border-border [&_td:last-child]:border-r-0 [&_th]:border-r [&_th]:border-border [&_th:last-child]:border-r-0"
            : ""
        }`}
      >
        {children}
      </table>
    </div>
  );
}

export function Th({ children, className = "" }: { children?: ReactNode; className?: string }) {
  return (
    <th
      className={`border-b border-border bg-gray-50/60 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted ${className}`}
    >
      {children}
    </th>
  );
}

export function Td({ children, className = "", colSpan }: { children?: ReactNode; className?: string; colSpan?: number }) {
  return (
    <td colSpan={colSpan} className={`border-b border-border px-4 py-3 align-middle ${className}`}>
      {children}
    </td>
  );
}

// ---- SortableTh (server-rendered link toggling ?sort=&dir=) ----
export function SortableTh({
  label,
  column,
  sort,
  dir,
  basePath,
  params = {},
  className = "",
}: {
  label: string;
  column: string;
  sort?: string;
  dir?: string;
  basePath: string;
  params?: Record<string, string | undefined>;
  className?: string;
}) {
  const active = sort === column;
  const nextDir = active && dir === "asc" ? "desc" : "asc";
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) if (v) qs.set(k, v);
  qs.set("sort", column);
  qs.set("dir", nextDir);
  const arrow = active ? (dir === "asc" ? "▲" : "▼") : "↕";
  return (
    <Th className={className}>
      <Link href={`${basePath}?${qs.toString()}`} className="inline-flex items-center gap-1 hover:text-ink">
        {label}
        <span className={active ? "text-brand-600" : "text-gray-300"}>{arrow}</span>
      </Link>
    </Th>
  );
}

// ---- Pagination footer ----
export function Pagination({
  meta,
  basePath,
  params = {},
}: {
  meta: { total: number; per_page: number; current_page: number; last_page: number };
  basePath: string;
  params?: Record<string, string | undefined>;
}) {
  const build = (page: number) => {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v) qs.set(k, v);
    qs.set("page", String(page));
    return `${basePath}?${qs.toString()}`;
  };
  const { current_page, last_page, total } = meta;
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm text-muted">
      <span>
        Menampilkan {total === 0 ? 0 : (current_page - 1) * meta.per_page + 1}–
        {Math.min(current_page * meta.per_page, total)} dari {total} · Halaman {current_page}/{last_page || 1}
      </span>
      <div className="flex gap-1.5">
        <PagerLink href={build(current_page - 1)} disabled={current_page <= 1}>
          ← Sebelumnya
        </PagerLink>
        <PagerLink href={build(current_page + 1)} disabled={current_page >= last_page}>
          Berikutnya →
        </PagerLink>
      </div>
    </div>
  );
}

function PagerLink({ href, disabled, children }: { href: string; disabled?: boolean; children: ReactNode }) {
  if (disabled)
    return (
      <span className="cursor-not-allowed rounded-lg border border-border px-3 py-1.5 text-xs text-gray-300">
        {children}
      </span>
    );
  return (
    <Link href={href} className="rounded-lg border border-border px-3 py-1.5 text-xs text-ink hover:bg-gray-50">
      {children}
    </Link>
  );
}

// ---- EmptyState ----
export function EmptyState({ title, hint }: { title: string; hint?: string }) {
  return (
    <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
      <div className="mb-3 grid h-12 w-12 place-items-center rounded-full bg-brand-50 text-brand-600">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M12 5v14M5 12h14" strokeLinecap="round" />
        </svg>
      </div>
      <p className="text-sm font-medium text-ink">{title}</p>
      {hint && <p className="mt-1 max-w-sm text-sm text-muted">{hint}</p>}
    </div>
  );
}

// ---- Stat tile ----
export function Stat({
  label,
  value,
  hint,
}: {
  label: string;
  value: ReactNode;
  hint?: string;
}) {
  return (
    <Card className="animate-fade-up">
      <CardBody>
        <p className="text-xs font-medium uppercase tracking-wide text-muted">{label}</p>
        <p className="mt-2 text-3xl font-semibold tracking-tight text-ink">{value}</p>
        {hint && <p className="mt-1 text-xs text-muted">{hint}</p>}
      </CardBody>
    </Card>
  );
}
