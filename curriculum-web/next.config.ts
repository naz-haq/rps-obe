import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Bundel mandiri (.next/standalone -> server.js) agar image Docker ramping.
  output: "standalone",

  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "Strict-Transport-Security", value: "max-age=31536000; includeSubDomains" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "SAMEORIGIN" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
        ],
      },
    ];
  },

  // Izinkan akses dev-server dari domain tunnel (VS Code / Cloudflare / ngrok /
  // GitHub Codespaces) supaya aset _next tidak diblokir saat diuji orang lain.
  allowedDevOrigins: ["*.devtunnels.ms", "*.trycloudflare.com", "*.ngrok-free.app", "*.app.github.dev"],

  // Loloskan Server Actions dari cek CSRF saat diakses via tunnel/reverse proxy.
  // `**` cocok untuk subdomain berlapis (mis. xxxx-3010.asse.devtunnels.ms).
  // localhost/127.0.0.1 diperlukan karena VS Code port forwarding menyuntikkan
  // header `x-forwarded-host` = domain tunnel walau halaman dibuka dari localhost,
  // sehingga `Origin` (localhost) tidak cocok dengan host yang diteruskan.
  experimental: {
    // Middleware (proxy) Next mem-buffer body request; default 10MB memotong
    // unggahan besar -> "Unexpected end of form". Naikkan agar PDF besar utuh.
    proxyClientMaxBodySize: "50mb",
    serverActions: {
      // Batas body server action (default 1MB) dinaikkan untuk unggah dokumen.
      bodySizeLimit: "50mb",
      allowedOrigins: [
        "rps.pharm.web.id",
        "*.pharm.web.id",
        "**.devtunnels.ms",
        "**.trycloudflare.com",
        "**.ngrok-free.app",
        "**.app.github.dev",
        "localhost:3010",
        "127.0.0.1:3010",
      ],
    },
  },

};

export default nextConfig;
