import type { NextConfig } from "next";

// Origin backend Laravel (curriculum-service). Bisa di-override via env.
const backendOrigin = process.env.BACKEND_ORIGIN ?? "http://127.0.0.1:8100";

const nextConfig: NextConfig = {
  // Izinkan akses dev-server dari domain tunnel (VS Code / Cloudflare / ngrok)
  // supaya aset _next tidak diblokir saat diuji orang lain.
  allowedDevOrigins: ["*.devtunnels.ms", "*.trycloudflare.com", "*.ngrok-free.app"],

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
