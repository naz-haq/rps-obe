/**
 * Identitas & branding aplikasi — UBAH DI SATU TEMPAT INI.
 *
 * LOGO:
 *   1. Letakkan berkas logo di folder `curriculum-web/public/`
 *      (mis. `public/logo.png` atau `public/logo.svg`).
 *   2. Isi `logoUrl` di bawah dengan path-nya, mis. "/logo.png".
 *   3. Biarkan kosong ("") untuk memakai ikon bawaan.
 *
 * FOOTER:
 *   Ubah teks/hak cipta/tautan pada objek `footer` di bawah.
 */
export const branding = {
  appName: "Curriculum",
  appTagline: "OBE · RPS Generator",
  institution: "Fakultas Farmasi",

  // Path logo di dalam folder public/. Kosongkan untuk ikon bawaan.
  logoUrl: "/logo.png",

  // Kalimat pemasaran singkat di panel login.
  loginHeadline: "Penyusunan RPS berbasis OBE, dibantu AI.",
  loginPoints: [
    "Peta kurikulum & keselarasan CPL–CPMK",
    "Generator RPS per-tahap dengan validasi",
    "Persetujuan, OBAEI, dan tata kelola terpadu",
  ] as string[],

  footer: {
    copyright: "© 2026 Fakultas Farmasi",
    text: "Sistem Penyusunan RPS OBE",
    links: [] as { label: string; href: string }[],
  },
};
