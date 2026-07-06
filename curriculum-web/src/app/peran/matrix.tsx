"use client";

import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import { buttonClass } from "@/components/ui";
import type { RbacGroup, RoleData } from "@/lib/api";
import { createRole, deleteRole, saveRolePermissions } from "./actions";

const PROTECTED = "super-admin";

type PermMap = Record<number, Set<string>>;

function clone(map: PermMap): PermMap {
  const out: PermMap = {};
  for (const [k, v] of Object.entries(map)) out[Number(k)] = new Set(v);
  return out;
}

function fromRoles(roles: RoleData[]): PermMap {
  const m: PermMap = {};
  for (const r of roles) m[r.id] = new Set(r.permissions);
  return m;
}

function sameSet(a: Set<string>, b: Set<string>): boolean {
  if (a.size !== b.size) return false;
  for (const x of a) if (!b.has(x)) return false;
  return true;
}

export function RoleMatrix({ roles, groups }: { roles: RoleData[]; groups: RbacGroup[] }) {
  const router = useRouter();
  const [current, setCurrent] = useState<PermMap>(() => fromRoles(roles));
  const [saved, setSaved] = useState<PermMap>(() => fromRoles(roles));
  const [pending, startTransition] = useTransition();
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  const editableRoles = roles.filter((r) => r.name !== PROTECTED);

  const dirtyIds = editableRoles
    .filter((r) => !sameSet(current[r.id] ?? new Set(), saved[r.id] ?? new Set()))
    .map((r) => r.id);
  const dirty = dirtyIds.length > 0;

  function toggle(roleId: number, perm: string) {
    setMsg(null);
    setErr(null);
    setCurrent((prev) => {
      const next = clone(prev);
      const set = next[roleId] ?? new Set<string>();
      if (set.has(perm)) set.delete(perm);
      else set.add(perm);
      next[roleId] = set;
      return next;
    });
  }

  function simpan() {
    setMsg(null);
    setErr(null);
    startTransition(async () => {
      const gagal: string[] = [];
      for (const id of dirtyIds) {
        const role = roles.find((r) => r.id === id);
        const res = await saveRolePermissions(id, Array.from(current[id] ?? []));
        if (!res.ok) gagal.push(`${role?.label ?? id}: ${res.message ?? "gagal"}`);
      }
      if (gagal.length) {
        setErr(gagal.join(" · "));
      } else {
        setSaved(clone(current));
        setMsg("Perubahan hak akses tersimpan.");
      }
    });
  }

  function batalkan() {
    setCurrent(clone(saved));
    setMsg(null);
    setErr(null);
  }

  function tambahPeran() {
    const nama = window.prompt("Nama peran baru (mis. Reviewer Eksternal):");
    if (!nama?.trim()) return;
    startTransition(async () => {
      const res = await createRole(nama.trim());
      if (res.ok) router.refresh();
      else setErr(res.message ?? "Gagal menambah peran.");
    });
  }

  function hapusPeran(role: RoleData) {
    if (role.users_count > 0) {
      setErr(`Peran "${role.label}" masih dipakai ${role.users_count} pengguna.`);
      return;
    }
    if (!window.confirm(`Hapus peran "${role.label}"?`)) return;
    startTransition(async () => {
      const res = await deleteRole(role.id);
      if (res.ok) router.refresh();
      else setErr(res.message ?? "Gagal menghapus peran.");
    });
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button type="button" onClick={tambahPeran} disabled={pending} className={buttonClass("secondary", "sm")}>
          + Tambah Peran
        </button>
        <div className="flex items-center gap-2">
          {msg && <span className="text-xs text-green-600">{msg}</span>}
          {err && <span className="text-xs text-red-600">{err}</span>}
          {dirty && (
            <button type="button" onClick={batalkan} disabled={pending} className={buttonClass("secondary", "sm")}>
              Batalkan
            </button>
          )}
          <button
            type="button"
            onClick={simpan}
            disabled={!dirty || pending}
            className={buttonClass("primary", "sm")}
          >
            {pending ? "Menyimpan…" : dirty ? `Simpan (${dirtyIds.length})` : "Tersimpan"}
          </button>
        </div>
      </div>

      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="bg-gray-50">
              <th className="sticky left-0 z-10 min-w-[16rem] border-b border-r border-border bg-gray-50 px-3 py-2 text-left font-semibold text-ink">
                Izin
              </th>
              {roles.map((r) => (
                <th key={r.id} className="border-b border-r border-border px-3 py-2 text-center align-bottom">
                  <div className="whitespace-nowrap text-xs font-semibold text-ink">{r.label}</div>
                  <div className="text-[10px] font-normal text-muted">{r.users_count} pengguna</div>
                  {!r.bawaan && r.name !== PROTECTED && (
                    <button
                      type="button"
                      onClick={() => hapusPeran(r)}
                      className="mt-1 text-[10px] text-red-500 hover:underline"
                    >
                      hapus
                    </button>
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {groups.map((g) => (
              <GroupRows
                key={g.key}
                group={g}
                roles={roles}
                current={current}
                onToggle={toggle}
              />
            ))}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-muted">
        Peran <strong>Super Admin</strong> selalu memiliki seluruh izin dan tidak dapat diubah.
      </p>
    </div>
  );
}

function GroupRows({
  group,
  roles,
  current,
  onToggle,
}: {
  group: RbacGroup;
  roles: RoleData[];
  current: PermMap;
  onToggle: (roleId: number, perm: string) => void;
}) {
  return (
    <>
      <tr className="bg-brand-50/60">
        <td
          colSpan={roles.length + 1}
          className="sticky left-0 border-b border-border px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-brand-700"
        >
          {group.label}
        </td>
      </tr>
      {group.permissions.map((p) => (
        <tr key={p.name} className="hover:bg-gray-50/60">
          <td className="sticky left-0 z-10 border-b border-r border-border bg-surface px-3 py-1.5 text-ink">
            {p.label}
            <span className="ml-1 text-[10px] text-gray-400">{p.name}</span>
          </td>
          {roles.map((r) => {
            const isSuper = r.name === PROTECTED;
            const checked = isSuper || (current[r.id]?.has(p.name) ?? false);
            return (
              <td key={r.id} className="border-b border-r border-border px-3 py-1.5 text-center">
                <input
                  type="checkbox"
                  checked={checked}
                  disabled={isSuper}
                  onChange={() => onToggle(r.id, p.name)}
                  className="h-4 w-4 cursor-pointer accent-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                />
              </td>
            );
          })}
        </tr>
      ))}
    </>
  );
}
