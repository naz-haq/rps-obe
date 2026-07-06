import type { NextConfig } from "next";

// Origin backend Laravel (curriculum-service). Bisa di-override via env.
const backendOrigin = process.env.BACKEND_ORIGIN ?? "http://127.0.0.1:8100";

const nextConfig: NextConfig = {
  // Izinkan akses dev-server dari domain tunnel (VS Code / Cloudflare / ngrok /
  // GitHub Codespaces) supaya aset _next tidak diblokir saat diuji orang lain.
  allowedDevOrigins: ["*.devtunnels.ms", "*.trycloudflare.com", "*.ngrok-free.app", "*.app.github.dev"],

  // Loloskan Server Actions dari cek CSRF saat diakses via tunnel/reverse proxy.
  // `**` cocok untuk subdomain berlapis (mis. xxxx-3010.asse.devtunnels.ms).
  // localhost/127.0.0.1 diperlukan karena VS Code port forwarding menyuntikkan
  // header `x-forwarded-host` = domain tunnel walau halaman dibuka dari localhost,
  // sehingga `Origin` (localhost) tidak cocok dengan host yang diteruskan.
  experimental: {
    serverActions: {
      allowedOrigins: [
        "**.devtunnels.ms",
        "**.trycloudflare.com",
        "**.ngrok-free.app",
        "**.app.github.dev",
        "localhost:3010",
        "127.0.0.1:3010",
      ],
    },
  },

  // Proxy: tautan unduhan/cetak dari browser (/backend/...) diteruskan ke
  // backend Laravel. Dengan begini tester cukup memakai SATU URL (frontend).
  async rewrites() {
    return [
      {
        source: "/backend/:path*",
        destination: `${backendOrigin}/:path*`,
      },
    ];
  },
};

export default nextConfig;
