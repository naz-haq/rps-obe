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
 *   Hanya dua teks: `copyright` (bar footer aplikasi) & `text` (footer login).
 */
export const branding = {
  appName: "Curricula",
  appVersion: "0.1",
  appTagline: "OBE - RPS Generator",
  institution: "",

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
    // Hak cipta — tampil di bar footer aplikasi (kiri bawah).
    copyright: "© 2026 QTech. All rights reserved.",
    // Label sistem — tampil di footer halaman login.
    text: "Sistem Penyusunan RPS OBE",
  },
};
