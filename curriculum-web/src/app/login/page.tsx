import { Logo, footerText } from "@/components/branding";
import { branding } from "@/lib/branding";
import { LoginForm } from "./login-form";

export default function LoginPage() {
  // Dibaca saat runtime (server component) agar bisa dikonfigurasi lewat env
  // container tanpa rebuild. Kosongkan untuk menonaktifkan Turnstile.
  const turnstileSiteKey = process.env.TURNSTILE_SITE_KEY || undefined;

  return (
    <div className="grid min-h-screen place-items-center bg-gray-50 px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex flex-col items-center text-center">
          <Logo size={72} className="mb-3" />
          <h1 className="text-xl font-semibold text-ink">{branding.appName}</h1>
          <p className="text-sm text-muted">Masuk untuk melanjutkan ke {branding.appName}.</p>
        </div>

        <LoginForm turnstileSiteKey={turnstileSiteKey} />

        <p className="mt-4 text-center text-xs text-muted">{footerText}</p>
      </div>
    </div>
  );
}
