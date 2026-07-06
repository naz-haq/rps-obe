"use client";

import { useActionState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Modal, Field, SelectField } from "@/components/modal";
import { buttonClass } from "@/components/ui";
import type { ApiResult, InstitusiRingkas, RoleData, UserAccount } from "@/lib/api";
import { createUser, updateUser, deleteUser } from "./actions";

type State = ApiResult | null;

function RoleChecklist({ roles, selected }: { roles: RoleData[]; selected: string[] }) {
  return (
    <div>
      <span className="mb-1 block text-xs font-medium text-ink">Peran</span>
      <div className="grid grid-cols-2 gap-1.5 rounded-lg border border-border p-3">
        {roles.map((r) => (
          <label key={r.id} className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              name="roles"
              value={r.name}
              defaultChecked={selected.includes(r.name)}
              className="h-4 w-4 accent-brand-600"
            />
            {r.label}
          </label>
        ))}
      </div>
    </div>
  );
}

function UserFields({
  roles,
  institusi,
  user,
}: {
  roles: RoleData[];
  institusi: InstitusiRingkas[];
  user?: UserAccount;
}) {
  const institusiOpts = [
    { value: "", label: "— Tanpa unit —" },
    ...institusi.map((i) => ({ value: String(i.id), label: `${i.nama} (${i.jenis})` })),
  ];
  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-3">
        <Field label="Nama" name="name" required defaultValue={user?.name ?? ""} placeholder="Dr. Nama Lengkap" />
        <Field label="NIDN" name="nidn" required defaultValue={user?.nidn ?? ""} placeholder="Untuk login" />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Email (opsional)" name="email" type="email" defaultValue={user?.email ?? ""} placeholder="nama@rps.local" />
        <Field label="Jabatan" name="jabatan" defaultValue={user?.jabatan ?? ""} placeholder="mis. Kaprodi" />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <SelectField
          label="Unit / Institusi"
          name="institusi_id"
          options={institusiOpts}
          defaultValue={user?.institusi_id ? String(user.institusi_id) : ""}
        />
        <Field
          label={user ? "Kata Sandi (kosongkan bila tetap)" : "Kata Sandi"}
          name="password"
          type="password"
          required={!user}
          placeholder={user ? "••••••" : "Minimal 8 karakter"}
        />
      </div>
      <RoleChecklist roles={roles} selected={user?.roles ?? []} />
      <label className="flex items-center gap-2 text-sm text-ink">
        <input
          type="checkbox"
          name="is_active"
          defaultChecked={user ? user.is_active : true}
          className="h-4 w-4 accent-brand-600"
        />
        Akun aktif
      </label>
    </div>
  );
}

export function CreateUserButton({ roles, institusi }: { roles: RoleData[]; institusi: InstitusiRingkas[] }) {
  return (
    <Modal trigger="+ Tambah Pengguna" title="Tambah Pengguna" size="lg">
      {(close) => <CreateForm roles={roles} institusi={institusi} close={close} />}
    </Modal>
  );
}

function CreateForm({
  roles,
  institusi,
  close,
}: {
  roles: RoleData[];
  institusi: InstitusiRingkas[];
  close: () => void;
}) {
  const router = useRouter();
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => createUser(fd), null);
  useEffect(() => {
    if (state?.ok) {
      router.refresh();
      close();
    }
  }, [state, close, router]);
  return (
    <form action={action} className="space-y-3">
      <UserFields roles={roles} institusi={institusi} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("primary")}>Simpan</button>
      </div>
    </form>
  );
}

export function EditUserButton({
  user,
  roles,
  institusi,
}: {
  user: UserAccount;
  roles: RoleData[];
  institusi: InstitusiRingkas[];
}) {
  return (
    <Modal trigger="Ubah" title="Ubah Pengguna" triggerVariant="ghost" triggerSize="sm" size="lg">
      {(close) => <EditForm user={user} roles={roles} institusi={institusi} close={close} />}
    </Modal>
  );
}

function EditForm({
  user,
  roles,
  institusi,
  close,
}: {
  user: UserAccount;
  roles: RoleData[];
  institusi: InstitusiRingkas[];
  close: () => void;
}) {
  const router = useRouter();
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => updateUser(fd), null);
  useEffect(() => {
    if (state?.ok) {
      router.refresh();
      close();
    }
  }, [state, close, router]);
  return (
    <form action={action} className="space-y-3">
      <input type="hidden" name="id" value={user.id} />
      <UserFields roles={roles} institusi={institusi} user={user} />
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2 pt-1">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("primary")}>Simpan</button>
      </div>
    </form>
  );
}

export function DeleteUserButton({ user }: { user: UserAccount }) {
  const router = useRouter();
  return (
    <Modal trigger="Hapus" title="Hapus Pengguna" triggerVariant="danger" triggerSize="sm">
      {(close) => <DeleteForm user={user} close={close} onDone={() => router.refresh()} />}
    </Modal>
  );
}

function DeleteForm({ user, close, onDone }: { user: UserAccount; close: () => void; onDone: () => void }) {
  const [state, action] = useActionState<State, FormData>(async (_prev, fd) => deleteUser(fd), null);
  useEffect(() => {
    if (state?.ok) {
      onDone();
      close();
    }
  }, [state, close, onDone]);
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="id" value={user.id} />
      <p className="text-sm text-muted">
        Hapus pengguna <span className="font-medium text-ink">{user.name}</span> (NIDN {user.nidn ?? "—"})?
      </p>
      {state && !state.ok && <p className="text-xs text-red-600">{state.message}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={close} className={buttonClass("secondary")}>Batal</button>
        <button type="submit" className={buttonClass("danger")}>Ya, hapus</button>
      </div>
    </form>
  );
}
