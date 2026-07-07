"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState, type ReactNode } from "react";
import type { AuthUser } from "@/lib/auth";
import { logoutAction } from "@/app/logout/actions";
import { Logo, AppFooter } from "@/components/branding";
import { branding } from "@/lib/branding";

type NavItem = { href: string; label: string; icon: ReactNode; perm: string };
type NavSection = { title: string; items: NavItem[] };

const sections: NavSection[] = [
  {
    title: "Umum",
    items: [
      { href: "/dashboard", label: "Beranda", icon: <IconHome />, perm: "dashboard.view" },
    ],
  },
  {
    title: "Acuan & Aturan",
    items: [
      { href: "/konfigurasi-aturan", label: "Konfigurasi Aturan", icon: <IconSliders />, perm: "konfigurasi-aturan.view" },
      { href: "/taksonomi", label: "Taksonomi", icon: <IconLayers />, perm: "taksonomi.view" },
      { href: "/dokumen-rujukan", label: "Dokumen Rujukan", icon: <IconDoc />, perm: "dokumen-rujukan.view" },
      { href: "/checklist-acuan", label: "Checklist Acuan", icon: <IconCheck />, perm: "checklist-acuan.view" },
    ],
  },
  {
    title: "Kurikulum",
    items: [
      { href: "/kurikulum", label: "Peta Kurikulum", icon: <IconGrid />, perm: "kurikulum.view" },
      { href: "/validasi-overlap", label: "Validator Overlap", icon: <IconAlert />, perm: "overlap.view" },
    ],
  },
  {
    title: "RPS",
    items: [
      { href: "/generator", label: "Generator RPS", icon: <IconSpark />, perm: "generator.view" },
      { href: "/rps", label: "Dokumen RPS", icon: <IconDoc />, perm: "rps.view" },
      { href: "/persetujuan", label: "Persetujuan", icon: <IconCheck />, perm: "persetujuan.view" },
    ],
  },
  {
    title: "Evaluasi & Monitoring",
    items: [
      { href: "/obaei", label: "OBAEI", icon: <IconChart />, perm: "obaei.view" },
      { href: "/governance", label: "Tata Kelola", icon: <IconShield />, perm: "governance.view" },
    ],
  },
  {
    title: "Pengaturan",
    items: [
      { href: "/pengaturan-ai", label: "Konfigurasi AI", icon: <IconCpu />, perm: "konfigurasi-ai.view" },
      { href: "/prompts", label: "Prompt AI", icon: <IconChat />, perm: "prompt-ai.view" },
      { href: "/template-rps", label: "Template RPS", icon: <IconDoc />, perm: "template-rps.view" },
    ],
  },
  {
    title: "Administrasi",
    items: [
      { href: "/prodi", label: "Prodi & Unit", icon: <IconBuilding />, perm: "prodi.view" },
      { href: "/pengguna", label: "Pengguna", icon: <IconUsers />, perm: "user.view" },
      { href: "/peran", label: "Peran & Hak Akses", icon: <IconKey />, perm: "role.view" },
    ],
  },
];

export function Shell({ children, user }: { children: ReactNode; user: AuthUser }) {
  const path = usePathname();

  // Saring menu sesuai izin pengguna; sembunyikan seksi yang kosong.
  const visibleSections = sections
    .map((s) => ({ ...s, items: s.items.filter((i) => user.permissions.includes(i.perm)) }))
    .filter((s) => s.items.length > 0);

  // Hanya tandai item yang paling spesifik (prefix terpanjang) agar induk seperti
  // /obaei tidak ikut aktif saat berada di /obaei/target.
  const bestMatch = visibleSections
    .flatMap((s) => s.items.map((i) => i.href))
    .filter((href) => path === href || path.startsWith(href + "/"))
    .sort((a, b) => b.length - a.length)[0];
  const isActive = (href: string) => href === bestMatch;

  // Kelompok menu bisa dibuka/tutup. Default semua terbuka; kelompok yang memuat
  // item aktif selalu dipaksa terbuka agar halaman aktif tak tersembunyi.
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
  const toggle = (title: string) =>
    setCollapsed((c) => ({ ...c, [title]: !c[title] }));

  return (
    <div className="flex min-h-screen">
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-border bg-surface md:flex">
        <div className="flex h-16 items-center gap-2.5 px-5">
          <Logo size={36} />
          <div className="leading-tight">
            <p className="text-base font-semibold text-ink">{branding.appName}</p>
            <p className="text-xs text-muted">{branding.appTagline}</p>
          </div>
        </div>

        <nav className="flex-1 space-y-2 overflow-y-auto px-3 py-4">
          {visibleSections.map((section) => {
            const hasActive = section.items.some((i) => isActive(i.href));
            const open = hasActive || !collapsed[section.title];
            return (
              <div key={section.title}>
                <button
                  type="button"
                  onClick={() => toggle(section.title)}
                  aria-expanded={open}
                  className="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400 transition hover:text-gray-600"
                >
                  <span>{section.title}</span>
                  <span className={`text-gray-300 transition-transform ${open ? "" : "-rotate-90"}`}>
                    <IconChevron />
                  </span>
                </button>
                {open && (
                  <ul className="mt-0.5 space-y-0.5">
                    {section.items.map((item) => {
                      const active = isActive(item.href);
                      return (
                        <li key={item.href}>
                          <Link
                            href={item.href}
                            className={`group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${
                              active
                                ? "bg-brand-50 text-brand-700"
                                : "text-gray-600 hover:bg-gray-50 hover:text-ink"
                            }`}
                          >
                            <span className={active ? "text-brand-600" : "text-gray-400 group-hover:text-gray-500"}>
                              {item.icon}
                            </span>
                            {item.label}
                          </Link>
                        </li>
                      );
                    })}
                  </ul>
                )}
              </div>
            );
          })}
        </nav>

        <div className="border-t border-border p-4">
          <form action={logoutAction}>
            <button
              type="submit"
              className="flex w-full items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-ink"
            >
              <IconLogout />
              Keluar
            </button>
          </form>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-border bg-surface/80 px-6 backdrop-blur md:px-8">
          <MobileBrand />
          <div className="ml-auto flex items-center gap-3">
            <Link
              href="/profil-saya"
              title="Edit profil"
              className="flex items-center gap-3 rounded-lg px-1.5 py-1 transition hover:bg-gray-50"
            >
              <div className="hidden text-right leading-tight sm:block">
                <p className="text-sm font-medium text-ink">{user.name}</p>
                <p className="text-xs text-muted">
                  {roleLabel(user.roles[0])}
                  {user.institusi ? ` · ${user.institusi.nama}` : ""}
                </p>
              </div>
              <div className="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                {initials(user.name)}
              </div>
            </Link>
            <form action={logoutAction} className="md:hidden">
              <button
                type="submit"
                title="Keluar"
                aria-label="Keluar"
                className="grid h-8 w-8 place-items-center rounded-lg border border-border text-gray-600 transition hover:bg-gray-50 hover:text-ink"
              >
                <IconLogout />
              </button>
            </form>
          </div>
        </header>
        <main className="w-full flex-1 px-6 py-8 md:px-8">{children}</main>
        <AppFooter />
      </div>
    </div>
  );
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? "")
    .join("");
}

const ROLE_LABELS: Record<string, string> = {
  "super-admin": "Super Admin",
  "admin-akademik": "Admin Akademik",
  "pimpinan-fakultas": "Pimpinan Fakultas",
  kaprodi: "Kaprodi/Sekprodi",
  "koordinator-mk": "Koordinator MK",
  dosen: "Dosen",
  stpmp: "STPMP (Mutu Prodi)",
  psmf: "PSMF (Mutu Fakultas)",
  lpm: "LPM (Mutu Universitas)",
};

function roleLabel(role?: string): string {
  if (!role) return "Tanpa peran";
  return ROLE_LABELS[role] ?? role;
}

function IconUsers() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
  );
}

function IconKey() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
      <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
    </svg>
  );
}

function IconBuilding() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
      <path d="M3 21h18M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" />
      <path d="M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1" />
    </svg>
  );
}

function IconLogout() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <path d="M16 17l5-5-5-5M21 12H9" />
    </svg>
  );
}

function MobileBrand() {
  return (
    <div className="flex items-center gap-2 md:hidden">
      <Logo size={32} rounded="rounded-lg" />
      <span className="text-sm font-semibold">{branding.appName}</span>
    </div>
  );
}

// ---- Ikon (stroke, 18px) ----
function IconBase({ children }: { children: ReactNode }) {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
      {children}
    </svg>
  );
}
function IconHome() {
  return <IconBase><path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /></IconBase>;
}
function IconGrid() {
  return <IconBase><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></IconBase>;
}
function IconSpark() {
  return <IconBase><path d="M12 3v4M12 17v4M3 12h4M17 12h4" /><path d="M12 8a4 4 0 0 0 4 4 4 4 0 0 0-4 4 4 4 0 0 0-4-4 4 4 0 0 0 4-4Z" /></IconBase>;
}
function IconDoc() {
  return <IconBase><path d="M6 2h9l4 4v16H6z" /><path d="M14 2v5h5" /><path d="M9 13h6M9 17h6" /></IconBase>;
}
function IconCpu() {
  return <IconBase><rect x="7" y="7" width="10" height="10" rx="2" /><path d="M4 10v4M20 10v4M10 4h4M10 20h4M2 12h2M20 12h2M12 2v2M12 20v2" /></IconBase>;
}

function IconChat() {
  return <IconBase><path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z" /></IconBase>;
}
function IconCheck() {
  return <IconBase><path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></IconBase>;
}
function IconSliders() {
  return <IconBase><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6" /></IconBase>;
}
function IconLayers() {
  return <IconBase><path d="M12 2 2 7l10 5 10-5-10-5Z" /><path d="m2 12 10 5 10-5" /><path d="m2 17 10 5 10-5" /></IconBase>;
}
function IconAlert() {
  return <IconBase><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></IconBase>;
}
function IconChart() {
  return <IconBase><path d="M3 3v18h18" /><path d="M7 15l3-4 3 2 4-6" /></IconBase>;
}
function IconShield() {
  return <IconBase><path d="M12 3 5 6v5c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z" /><path d="M9.5 12l2 2 3.5-4" /></IconBase>;
}
function IconChevron() {
  return (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
      <path d="m6 9 6 6 6-6" />
    </svg>
  );
}