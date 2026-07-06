<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Generator RPS Bertahap (Blueprint 7.4)
|--------------------------------------------------------------------------
|
| ATURAN KERAS: RPS TIDAK di-generate sekaligus. Setiap tahap = satu panggilan
| AI terpisah dengan skema keluaran JSON sempit. Tahap berikutnya hanya boleh
| berjalan setelah prasyaratnya (context_from) DISETUJUI/dikunci.
|
| 'jenis_output' dipetakan ke slot prompt (config/prompts.php) & ke override
| PROMPT_TEMPLATE (tenant boleh override; null=global). Teks prompt + skema
| keluaran TIDAK lagi di sini — dipusatkan di config/prompts.php.
|
*/

return [

    // Urutan tahap (gate berurutan)
    'pipeline' => ['cpmk', 'sub_cpmk', 'mingguan', 'penilaian'],

    // Status yang dianggap "terkunci" (memenuhi prasyarat tahap berikutnya)
    'locked_states' => ['accepted', 'edited', 'pinned'],

    // Grounding tiap tahap (Blueprint 7.5): validasi keluaran generate terhadap
    // DOKUMEN_CHUNK. Bila ada klaim tak-grounded, tahap diregenerasi otomatis
    // maksimal 'ai.grounding.auto_revisi_maks' kali memakai konteks pengganti,
    // lalu ditandai perlu_review. Dilewati bila tenant tak punya dokumen rujukan.
    'grounding' => [
        'enabled' => env('GENERATOR_GROUNDING', true),
    ],

    // Struktur pipeline. Teks prompt (system) & skema JSON per tahap ada di
    // config/prompts.php slot yang sama dengan 'jenis_output'.
    'stages' => [

        'cpmk' => [
            'label'        => 'CPMK',
            'jenis_output' => 'cpmk',
            'context_from' => [],
        ],

        'sub_cpmk' => [
            'label'        => 'Sub-CPMK + Indikator',
            'jenis_output' => 'sub_cpmk',
            'context_from' => ['cpmk'],
        ],

        'mingguan' => [
            'label'        => 'Rencana 16 Minggu',
            'jenis_output' => 'mingguan',
            'context_from' => ['cpmk', 'sub_cpmk'],
            // Keluaran terbesar (16 minggu) — beri anggaran token lebih besar
            // agar JSON tidak terpotong / balik kosong.
            'max_tokens'   => (int) env('GENERATOR_MAX_TOKENS_MINGGUAN', 8000),
        ],

        'penilaian' => [
            'label'        => 'Komponen Penilaian + Rubrik',
            'jenis_output' => 'penilaian',
            'context_from' => ['cpmk', 'sub_cpmk', 'mingguan'],
            'max_tokens'   => (int) env('GENERATOR_MAX_TOKENS_PENILAIAN', 6000),
        ],

    ],
];
