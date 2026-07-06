import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/auth";
import { PageHeader, Card, CardBody } from "@/components/ui";
import { ProfilForm, PasswordForm } from "./forms";

export default async function ProfilSayaPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login");

  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader
        title="Profil Saya"
        subtitle="Perbarui data akun serta ganti email dan kata sandi Anda."
      />

      <div className="space-y-6">
        <Card>
          <CardBody className="space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted">Identitas Akun</p>
            <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
              <dt className="text-muted">NIDN</dt>
              <dd className="text-ink">{user.nidn ?? "\u2014"}</dd>
              <dt className="text-muted">Jabatan</dt>
              <dd className="text-ink">{user.jabatan ?? "\u2014"}</dd>
              <dt className="text-muted">Unit / Institusi</dt>
              <dd className="text-ink">{user.institusi?.nama ?? "\u2014"}</dd>
            </dl>
            <p className="mt-2 text-xs text-muted">NIDN dan peran hanya dapat diubah oleh administrator.</p>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <h2 className="mb-4 text-sm font-semibold text-ink">Data Profil</h2>
            <ProfilForm nama={user.name} email={user.email ?? ""} />
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <h2 className="mb-1 text-sm font-semibold text-ink">Ubah Kata Sandi</h2>
            <p className="mb-4 text-xs text-muted">
              Setelah kata sandi diubah, sesi login lain akan keluar otomatis; sesi ini tetap aktif.
            </p>
            <PasswordForm />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
