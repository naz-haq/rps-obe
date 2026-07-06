import { branding } from "@/lib/branding";

function SparkMark() {
  return (
    <svg width="60%" height="60%" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 3l1.9 5.8H20l-4.9 3.6L17 18l-5-3.6L7 18l1.9-5.6L4 8.8h6.1L12 3z" />
    </svg>
  );
}

/**
 * Logo aplikasi. Menampilkan gambar dari `branding.logoUrl` bila diisi,
 * atau ikon bawaan (spark) sebagai fallback.
 */
export function Logo({
  size = 36,
  className = "",
  rounded = "rounded-lg",
}: {
  size?: number;
  className?: string;
  rounded?: string;
}) {
  if (branding.logoUrl) {
    return (
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={branding.logoUrl}
        alt={branding.appName}
        width={size}
        height={size}
        className={`${rounded} object-contain ${className}`}
      />
    );
  }
  return (
    <div
      style={{ width: size, height: size }}
      className={`grid place-items-center ${rounded} bg-brand-600 text-white shadow-sm ${className}`}
    >
      <SparkMark />
    </div>
  );
}

/** Footer aplikasi (dipakai di dalam Shell & halaman login). */
export function AppFooter({ className = "" }: { className?: string }) {
  const { copyright, text, links } = branding.footer;
  return (
    <footer className={`border-t border-border px-6 py-4 md:px-8 ${className}`}>
      <div className="flex flex-col items-center justify-between gap-2 text-xs text-muted sm:flex-row">
        <span>{copyright}</span>
        <div className="flex items-center gap-3">
          {links.map((l) => (
            <a key={l.href} href={l.href} className="transition hover:text-ink">
              {l.label}
            </a>
          ))}
          <span>{text}</span>
        </div>
      </div>
    </footer>
  );
}
