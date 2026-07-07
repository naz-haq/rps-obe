<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi AI — Curriculum Service (multi-provider, BYOK per tenant)
|--------------------------------------------------------------------------
|
| SUMBER KEBENARAN untuk: katalog model + harga, routing per-tugas, kategori
| grounding ketat, dan konfigurasi embedding. Kredensial nyata (API key) TIDAK
| disimpan di sini melainkan per tenant di tabel AI_KREDENSIAL (BYOK); nilai
| env di 'providers' hanya fallback tingkat server untuk dev.
|
| Harga = USD per 1 JUTA token (per Jul 2026; override via .env, dapat berubah).
| Prinsip "tidak overkill": Opus hanya untuk task 'eskalasi', bukan default.
|
*/

return [

    /*
    | Provider -> driver + base_url + api_key fallback (dev).
    | Kredensial tenant di AI_KREDENSIAL mengalahkan nilai di sini.
    */
    'providers' => [
        'openai' => [
            'driver'   => 'openai',
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key'  => env('OPENAI_API_KEY'),
        ],
        'anthropic' => [
            'driver'   => 'anthropic',
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'api_key'  => env('ANTHROPIC_API_KEY'),
        ],
        // Gemini & DeepSeek dipakai untuk JALUR SIMULASI (gratis/murah). Keduanya
        // mengekspos endpoint kompatibel-OpenAI, jadi memakai driver 'openai'
        // (cukup base_url berbeda) — TANPA kelas driver baru.
        'gemini' => [
            'driver'   => 'openai',
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
            'api_key'  => env('GEMINI_API_KEY'),
        ],
        'deepseek' => [
            'driver'   => 'openai',
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'api_key'  => env('DEEPSEEK_API_KEY'),
        ],
        // NVIDIA NIM (build.nvidia.com) — endpoint kompatibel-OpenAI, jadi memakai
        // driver 'openai' (cukup base_url berbeda). Trial gratis (ada rate-limit)
        // untuk JALUR SIMULASI. Satu base_url melayani semua model NVIDIA.
        'nvidia' => [
            'driver'   => 'openai',
            'base_url' => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
            'api_key'  => env('NVIDIA_API_KEY'),
        ],
        'mock' => [
            'driver'   => 'mock',
            'base_url' => null,
            'api_key'  => 'local',
        ],
    ],

    /*
    | Katalog model + harga (USD / 1M token). Nama model API dapat di-override
    | via .env agar tahan perubahan penamaan provider.
    */
    'models' => [
        'claude-sonnet-5' => [
            'provider' => 'anthropic',
            'model'    => env('AI_MODEL_SONNET', 'claude-sonnet-5'),
            'pricing'  => [
                'input'       => (float) env('PRICE_SONNET_IN', 2.00),
                'output'      => (float) env('PRICE_SONNET_OUT', 10.00),
                'cache_read'  => (float) env('PRICE_SONNET_CACHE_READ', 0.20),
                'cache_write' => (float) env('PRICE_SONNET_CACHE_WRITE', 2.50),
            ],
        ],
        'gpt-5-4' => [
            'provider' => 'openai',
            'model'    => env('AI_MODEL_GPT', 'gpt-5.4'),
            'pricing'  => [
                'input'       => (float) env('PRICE_GPT_IN', 2.50),
                'output'      => (float) env('PRICE_GPT_OUT', 15.00),
                'cache_read'  => (float) env('PRICE_GPT_CACHE_READ', 0.25),
                'cache_write' => (float) env('PRICE_GPT_CACHE_WRITE', 0.0),
            ],
        ],
        'gpt-5-4-mini' => [
            'provider' => 'openai',
            'model'    => env('AI_MODEL_GPT_MINI', 'gpt-5.4-mini'),
            'pricing'  => [
                'input'       => (float) env('PRICE_GPT_MINI_IN', 0.75),
                'output'      => (float) env('PRICE_GPT_MINI_OUT', 4.50),
                'cache_read'  => (float) env('PRICE_GPT_MINI_CACHE_READ', 0.075),
                'cache_write' => (float) env('PRICE_GPT_MINI_CACHE_WRITE', 0.0),
            ],
        ],
        'claude-opus-4-8' => [
            'provider' => 'anthropic',
            'model'    => env('AI_MODEL_OPUS', 'claude-opus-4-8'),
            'pricing'  => [
                'input'       => (float) env('PRICE_OPUS_IN', 5.00),
                'output'      => (float) env('PRICE_OPUS_OUT', 25.00),
                'cache_read'  => (float) env('PRICE_OPUS_CACHE_READ', 0.50),
                'cache_write' => (float) env('PRICE_OPUS_CACHE_WRITE', 6.25),
            ],
        ],
        // --- Model JALUR SIMULASI (murah/gratis) ---
        'gemini-flash' => [
            'provider' => 'gemini',
            'model'    => env('AI_MODEL_GEMINI_FLASH', 'gemini-2.5-flash'),
            'pricing'  => [
                'input'       => (float) env('PRICE_GEMINI_FLASH_IN', 0.10),
                'output'      => (float) env('PRICE_GEMINI_FLASH_OUT', 0.40),
                'cache_read'  => (float) env('PRICE_GEMINI_FLASH_CACHE_READ', 0.025),
                'cache_write' => (float) env('PRICE_GEMINI_FLASH_CACHE_WRITE', 0.0),
            ],
        ],
        // Flash-Lite: kuota gratis paling longgar + termurah. Ideal utk tes fungsi.
        'gemini-flash-lite' => [
            'provider' => 'gemini',
            'model'    => env('AI_MODEL_GEMINI_FLASH_LITE', 'gemini-2.5-flash-lite'),
            'pricing'  => [
                'input'       => (float) env('PRICE_GEMINI_FLASH_LITE_IN', 0.05),
                'output'      => (float) env('PRICE_GEMINI_FLASH_LITE_OUT', 0.20),
                'cache_read'  => (float) env('PRICE_GEMINI_FLASH_LITE_CACHE_READ', 0.0125),
                'cache_write' => (float) env('PRICE_GEMINI_FLASH_LITE_CACHE_WRITE', 0.0),
            ],
        ],
        'deepseek-chat' => [
            'provider' => 'deepseek',
            'model'    => env('AI_MODEL_DEEPSEEK_CHAT', 'deepseek-chat'),
            'pricing'  => [
                'input'       => (float) env('PRICE_DEEPSEEK_CHAT_IN', 0.27),
                'output'      => (float) env('PRICE_DEEPSEEK_CHAT_OUT', 1.10),
                'cache_read'  => (float) env('PRICE_DEEPSEEK_CHAT_CACHE_READ', 0.07),
                'cache_write' => (float) env('PRICE_DEEPSEEK_CHAT_CACHE_WRITE', 0.0),
            ],
        ],
        'deepseek-reasoner' => [
            'provider' => 'deepseek',
            'model'    => env('AI_MODEL_DEEPSEEK_REASONER', 'deepseek-reasoner'),
            'pricing'  => [
                'input'       => (float) env('PRICE_DEEPSEEK_REASONER_IN', 0.55),
                'output'      => (float) env('PRICE_DEEPSEEK_REASONER_OUT', 2.19),
                'cache_read'  => (float) env('PRICE_DEEPSEEK_REASONER_CACHE_READ', 0.14),
                'cache_write' => (float) env('PRICE_DEEPSEEK_REASONER_CACHE_WRITE', 0.0),
            ],
        ],
        // --- Model NVIDIA NIM (trial gratis; dipakai profil 'simulasi_nvidia') ---
        // Nama model API asli (mengandung '/') diletakkan di nilai env; KEY katalog
        // di bawah TIDAK boleh mengandung titik (dot-notation config).
        'deepseek-v4-flash' => [
            'provider' => 'nvidia',
            'model'    => env('AI_MODEL_NVIDIA_DS_FLASH', 'deepseek-ai/deepseek-v4-flash'),
            'pricing'  => ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
        ],
        'deepseek-v4-pro' => [
            'provider' => 'nvidia',
            'model'    => env('AI_MODEL_NVIDIA_DS_PRO', 'deepseek-ai/deepseek-v4-pro'),
            'pricing'  => ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
        ],
        'gpt-oss-120b' => [
            'provider' => 'nvidia',
            'model'    => env('AI_MODEL_NVIDIA_GPTOSS_120B', 'openai/gpt-oss-120b'),
            'pricing'  => ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
        ],
        'gpt-oss-20b' => [
            'provider' => 'nvidia',
            'model'    => env('AI_MODEL_NVIDIA_GPTOSS_20B', 'openai/gpt-oss-20b'),
            'pricing'  => ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
        ],
        'mock' => [
            'provider' => 'mock',
            'model'    => 'mock-1',
            'pricing'  => ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
        ],
    ],

    /*
    | Embedding untuk RAG (DOKUMEN_CHUNK.embedding). Claude tak punya model
    | embedding, jadi selalu OpenAI text-embedding-3-small (termurah, cukup).
    */
    'embedding' => [
        'provider'   => env('AI_EMBED_PROVIDER', 'openai'),
        'model'      => env('AI_EMBED_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('AI_EMBED_DIMENSIONS', 1536),
        'pricing'    => ['input' => (float) env('PRICE_EMBED_IN', 0.02), 'output' => 0.0],
    ],

    /*
    | Routing per-tugas (Blueprint 7.6). 'cross_provider_of' menandai tugas
    | yang WAJIB memakai provider BERBEDA dari tugas rujukan — dipakai validator
    | anti-halusinasi agar tidak "memvalidasi diri sendiri" (generator).
    */
    'default_task' => 'asistif',

    'tasks' => [
        'generate'       => ['model' => 'claude-sonnet-5', 'temperature' => 0.4, 'max_tokens' => 4000],
        'judge'          => ['model' => 'gpt-5-4',         'temperature' => 0.0, 'max_tokens' => 1500],
        'validator'      => ['model' => 'gpt-5-4-mini',    'temperature' => 0.0, 'max_tokens' => 1200, 'cross_provider_of' => 'generate'],
        'asistif'        => ['model' => 'gpt-5-4-mini',    'temperature' => 0.3, 'max_tokens' => 800],
        'ekstraksi'      => ['model' => 'gpt-5-4-mini',    'temperature' => 0.0, 'max_tokens' => 1500],
        'konversasional' => ['model' => 'gpt-5-4-mini',    'temperature' => 0.5, 'max_tokens' => 1500],
        'eskalasi'       => ['model' => 'claude-opus-4-8', 'temperature' => 0.3, 'max_tokens' => 4000],
    ],

    'default_params' => [
        'temperature' => 0.3,
        'max_tokens'  => 1500,
    ],

    /*
    | PROFIL AI — memilih SET model per-tugas yang aktif tanpa mengubah kode.
    | 'produksi'  = jalur primer (Claude/GPT, mutu tinggi, untuk go-live).
    | 'simulasi'  = jalur murah/gratis (Gemini/DeepSeek) untuk menguji alur &
    |               fungsi selama pengembangan.
    | Profil aktif default dari env AI_PROFILE, TETAPI dapat ditimpa saat runtime
    | lewat tabel AI_PENGATURAN (baris global institusi_id null, atau per-tenant)
    | sehingga peralihan simulasi<->produksi bisa dilakukan dari UI tanpa deploy.
    | Nilai tiap profil = task => key model (harus ada di 'models' di atas).
    | Task yang tak disebut di profil memakai default 'tasks.{task}.model'.
    */
    'active_profile' => env('AI_PROFILE', 'simulasi_nvidia'),

    'profiles' => [
        'produksi' => [
            'generate'       => 'claude-sonnet-5',
            'judge'          => 'gpt-5-4',
            'validator'      => 'gpt-5-4-mini',
            'asistif'        => 'gpt-5-4-mini',
            'ekstraksi'      => 'gpt-5-4-mini',
            'konversasional' => 'gpt-5-4-mini',
            'eskalasi'       => 'claude-opus-4-8',
        ],
        'simulasi' => [
            'generate'       => 'gemini-flash-lite',
            'judge'          => 'deepseek-chat',
            'validator'      => 'deepseek-chat', // beda provider dari generator (gemini) -> lolos aturan lintas-provider
            'asistif'        => 'gemini-flash-lite',
            'ekstraksi'      => 'gemini-flash-lite',
            'konversasional' => 'gemini-flash-lite',
            'eskalasi'       => 'deepseek-reasoner',
        ],
        // Jalur simulasi berbasis NVIDIA NIM (trial gratis). Generator & tugas
        // ringan memakai model NVIDIA (DeepSeek V4 / GPT-OSS); validator sengaja
        // di DeepSeek (provider != nvidia) agar aturan lintas-provider lolos tanpa
        // key tambahan selain yang sudah ada.
        'simulasi_nvidia' => [
            'generate'       => 'deepseek-v4-flash',
            'judge'          => 'gpt-oss-120b',
            'validator'      => 'deepseek-chat',
            'asistif'        => 'gpt-oss-20b',
            'ekstraksi'      => 'gpt-oss-20b',
            'konversasional' => 'gpt-oss-20b',
            'eskalasi'       => 'deepseek-v4-pro',
        ],
    ],

    /*
    | Grounding ketat (Blueprint 7.5): kategori BADAN_RUJUKAN.jenis yang klaim
    | normatifnya WAJIB tergrounding ke DOKUMEN_CHUNK/BUTIR_ACUAN.
    */
    'strict_categories' => ['regulasi_nasional', 'akreditasi', 'asosiasi_profesi'],

    /*
    | Parameter validator anti-halusinasi (mode 5). 'top_k'/'min_score' untuk
    | retrieval bukti (EmbeddingService::search); 'auto_revisi_maks' = batas
    | regenerasi otomatis saat klaim gagal grounding sebelum ditandai review.
    */
    'grounding' => [
        'top_k'            => (int) env('AI_GROUNDING_TOPK', 5),
        'min_score'        => (float) env('AI_GROUNDING_MIN_SCORE', 0.75),
        'auto_revisi_maks' => (int) env('AI_GROUNDING_AUTO_REVISI', 1),
    ],

    /*
    | Jika kredensial provider tidak tersedia (tak ada BYOK & tak ada env key),
    | pakai driver mock (khusus dev). Set false di produksi agar gagal jelas.
    */
    'fallback_to_mock' => (bool) env('AI_FALLBACK_MOCK', true),
];
