"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";

const TABS = [
  { href: "/obaei", label: "Ketercapaian CPL" },
  { href: "/obaei/target", label: "Target CPL" },
  { href: "/obaei/capaian", label: "Capaian Mahasiswa" },
  { href: "/obaei/evaluasi", label: "Evaluasi & Tindak Lanjut" },
];

export default function ObaeiLayout({ children }: { children: ReactNode }) {
  const path = usePathname();
  const isActive = (href: string) =>
    href === "/obaei" ? path === "/obaei" : path === href || path.startsWith(href + "/");

  return (
    <div>
      <div className="mb-6 flex flex-wrap gap-1 border-b border-border">
        {TABS.map((t) => {
          const active = isActive(t.href);
          return (
            <Link
              key={t.href}
              href={t.href}
              className={`-mb-px border-b-2 px-3.5 py-2 text-sm font-medium transition ${
                active
                  ? "border-brand-600 text-brand-700"
                  : "border-transparent text-gray-500 hover:text-ink"
              }`}
            >
              {t.label}
            </Link>
          );
        })}
      </div>
      {children}
    </div>
  );
}
