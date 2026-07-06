<?php

/**
 * Katalog RBAC — sumber tunggal untuk:
 *  - Seeder izin & peran (RbacSeeder)
 *  - Matriks ceklist Role × Izin di frontend (endpoint /rbac/katalog)
 *  - Pemetaan izin → menu (gating tampilan halaman)
 *
 * Izin memakai pola "modul.aksi". "view" = boleh membuka/melihat halaman;
 * "manage" = boleh tambah/ubah/hapus; aksi khusus mis. "persetujuan.approve".
 */
return [
    // Grup izin (untuk tampilan matriks ceklist yang rapi).
    'groups' => [
        'beranda' => [
            'label' => 'Beranda',
            'permissions' => [
                'dashboard.view' => 'Lihat beranda',
            ],
        ],
        'acuan' => [
            'label' => 'Acuan & Aturan',
            'permissions' => [
                'konfigurasi-aturan.view' => 'Lihat Konfigurasi Aturan',
                'konfigurasi-aturan.manage' => 'Kelola Konfigurasi Aturan',
                'taksonomi.view' => 'Lihat Taksonomi',
                'taksonomi.manage' => 'Kelola Taksonomi',
                'dokumen-rujukan.view' => 'Lihat Dokumen Rujukan',
                'dokumen-rujukan.manage' => 'Kelola Dokumen Rujukan',
                'checklist-acuan.view' => 'Lihat Checklist Acuan',
                'checklist-acuan.manage' => 'Kelola Checklist Acuan',
            ],
        ],
        'kurikulum' => [
            'label' => 'Kurikulum',
            'permissions' => [
                'kurikulum.view' => 'Lihat Peta Kurikulum',
                'kurikulum.manage' => 'Kelola Peta Kurikulum',
                'overlap.view' => 'Lihat Validator Overlap',
                'overlap.manage' => 'Jalankan & tinjau Overlap',
            ],
        ],
        'rps' => [
            'label' => 'RPS',
            'permissions' => [
                'generator.view' => 'Lihat RPS Generator',
                'generator.manage' => 'Susun/Generate RPS',
                'rps.view' => 'Lihat Dokumen RPS',
                'rps.manage' => 'Kelola Dokumen RPS',
                'persetujuan.view' => 'Lihat Persetujuan',
                'persetujuan.approve' => 'Setujui / Minta Revisi RPS',
            ],
        ],
        'evaluasi' => [
            'label' => 'Evaluasi & Monitoring',
            'permissions' => [
                'obaei.view' => 'Lihat OBAEI',
                'obaei.manage' => 'Kelola OBAEI & Tindak Lanjut',
                'governance.view' => 'Lihat Tata Kelola',
            ],
        ],
        'pengaturan' => [
            'label' => 'Pengaturan',
            'permissions' => [
                'konfigurasi-ai.view' => 'Lihat Konfigurasi AI',
                'konfigurasi-ai.manage' => 'Kelola Konfigurasi AI',
                'prompt-ai.view' => 'Lihat Prompt AI',
                'prompt-ai.manage' => 'Kelola Prompt AI',
                'template-rps.view' => 'Lihat Template RPS',
                'template-rps.manage' => 'Kelola Template RPS',
            ],
        ],
        'administrasi' => [
            'label' => 'Administrasi Sistem',
            'permissions' => [
                'prodi.view' => 'Lihat Prodi & Unit',
                'prodi.manage' => 'Kelola Prodi & Unit',
                'user.view' => 'Lihat Pengguna',
                'user.manage' => 'Kelola Pengguna',
                'role.view' => 'Lihat Peran & Hak Akses',
                'role.manage' => 'Kelola Peran & Hak Akses',
            ],
        ],
    ],

    // Peran bawaan + izin default. 'all' => true berarti seluruh izin.
    // Semua ini dapat diubah kapan saja lewat matriks ceklist.
    'roles' => [
        'super-admin' => [
            'label' => 'Super Admin',
            'deskripsi' => 'Akses penuh seluruh sistem.',
            'all' => true,
        ],
        'admin-akademik' => [
            'label' => 'Admin Akademik',
            'deskripsi' => 'Mengelola data akademik, kurikulum, RPS, dan pengaturan.',
            'permissions' => [
                'dashboard.view',
                'prodi.view',
                'prodi.manage',
                'konfigurasi-aturan.view',
                'konfigurasi-aturan.manage',
                'taksonomi.view',
                'taksonomi.manage',
                'dokumen-rujukan.view',
                'dokumen-rujukan.manage',
                'checklist-acuan.view',
                'checklist-acuan.manage',
                'kurikulum.view',
                'kurikulum.manage',
                'overlap.view',
                'overlap.manage',
                'generator.view',
                'generator.manage',
                'rps.view',
                'rps.manage',
                'persetujuan.view',
                'obaei.view',
                'obaei.manage',
                'governance.view',
                'konfigurasi-ai.view',
                'konfigurasi-ai.manage',
                'prompt-ai.view',
                'prompt-ai.manage',
                'template-rps.view',
                'template-rps.manage',
            ],
        ],
        'pimpinan-fakultas' => [
            'label' => 'Pimpinan Fakultas',
            'deskripsi' => 'Dekan/Wakil Dekan — pemantauan menyeluruh.',
            'permissions' => [
                'dashboard.view',
                'kurikulum.view',
                'overlap.view',
                'generator.view',
                'rps.view',
                'persetujuan.view',
                'persetujuan.approve',
                'obaei.view',
                'governance.view',
                'checklist-acuan.view',
                'taksonomi.view',
                'konfigurasi-aturan.view',
            ],
        ],
        'kaprodi' => [
            'label' => 'Kaprodi & Sekprodi',
            'deskripsi' => 'Mengelola kurikulum prodi & menyetujui RPS.',
            'permissions' => [
                'dashboard.view',
                'konfigurasi-aturan.view',
                'taksonomi.view',
                'checklist-acuan.view',
                'checklist-acuan.manage',
                'kurikulum.view',
                'kurikulum.manage',
                'overlap.view',
                'overlap.manage',
                'generator.view',
                'generator.manage',
                'rps.view',
                'rps.manage',
                'persetujuan.view',
                'persetujuan.approve',
                'obaei.view',
                'obaei.manage',
                'template-rps.view',
            ],
        ],
        'koordinator-mk' => [
            'label' => 'Koordinator MK',
            'deskripsi' => 'Menyusun RPS mata kuliah yang dikoordinir.',
            'permissions' => [
                'dashboard.view',
                'konfigurasi-aturan.view',
                'taksonomi.view',
                'checklist-acuan.view',
                'kurikulum.view',
                'generator.view',
                'generator.manage',
                'rps.view',
                'rps.manage',
                'persetujuan.view',
                'obaei.view',
                'template-rps.view',
            ],
        ],
        'dosen' => [
            'label' => 'Dosen (Tim Penyusun)',
            'deskripsi' => 'Menyusun draf RPS.',
            'permissions' => [
                'dashboard.view',
                'taksonomi.view',
                'checklist-acuan.view',
                'kurikulum.view',
                'generator.view',
                'generator.manage',
                'rps.view',
                'template-rps.view',
            ],
        ],
        'stpmp' => [
            'label' => 'STPMP (Mutu Prodi)',
            'deskripsi' => 'Peninjau mutu tingkat prodi.',
            'permissions' => [
                'dashboard.view',
                'kurikulum.view',
                'overlap.view',
                'checklist-acuan.view',
                'generator.view',
                'rps.view',
                'persetujuan.view',
                'persetujuan.approve',
                'obaei.view',
                'governance.view',
            ],
        ],
        'psmf' => [
            'label' => 'PSMF (Mutu Fakultas)',
            'deskripsi' => 'Peninjau mutu tingkat fakultas.',
            'permissions' => [
                'dashboard.view',
                'kurikulum.view',
                'overlap.view',
                'checklist-acuan.view',
                'generator.view',
                'rps.view',
                'persetujuan.view',
                'persetujuan.approve',
                'obaei.view',
                'governance.view',
            ],
        ],
        'lpm' => [
            'label' => 'LPM (Mutu Universitas)',
            'deskripsi' => 'Pemantauan mutu tingkat universitas.',
            'permissions' => [
                'dashboard.view',
                'kurikulum.view',
                'rps.view',
                'obaei.view',
                'governance.view',
            ],
        ],
    ],
];
