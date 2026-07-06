"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";

const TABS = [
  { href: "/governance", label: "Dashboard Biaya" },
  { href: "/governance/audit", label: "Audit Log" },
  { href: "/governance/notifikasi", label: "Notifikasi" },
];

export default function GovernanceLayout({ children }: { children: ReactNode }) {
  const path = usePathname();
  const isActive = (href: string) =>
    href === "/governance" ? path === "/governance" : path === href || path.startsWith(href + "/");

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
