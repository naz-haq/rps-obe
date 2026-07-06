<?php

namespace App\Services\Rps;

use App\Services\Ai\AiService;
use App\Services\Ai\PromptRepository;

/**
 * Chat konsultan kurikulum OBE (fitur #7, mengadopsi rps-obe-builder). Sadar
 * konteks RPS aktif (snapshot) sehingga jawabannya kontekstual. Riwayat pesan
 * diratakan menjadi satu transkrip karena AiService memakai satu system+prompt.
 */
class KurikulumChatService
{
    public function __construct(
        private AiService $ai,
        private PromptRepository $prompts,
    ) {}

    /**
     * @param  array<int,array{sender:string,text:string}>  $messages
     * @param  array|null  $snapshot  konteks RPS (opsional) dari RpsSnapshot
     */
    public function reply(int $institusiId, array $messages, ?array $snapshot = null): string
    {
        $prompt = $this->prompts->resolve('chat', $institusiId);

        $system = $prompt['system'];
        if ($snapshot) {
            $system .= "\n\n=== KONTEKS RPS SAAT INI ===\n"
                . json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\nGunakan konteks di atas untuk menjawab secara spesifik bila relevan.";
        }

        $transkrip = [];
        foreach ($messages as $m) {
            $peran = ($m['sender'] ?? 'user') === 'user' ? 'Dosen' : 'Asisten';
            $teks = trim((string) ($m['text'] ?? ''));
            if ($teks !== '') {
                $transkrip[] = "{$peran}: {$teks}";
            }
        }
        $transkrip[] = 'Asisten:';

        $outcome = $this->ai->run('asistif', $system, implode("\n", $transkrip), [
            'institusi_id' => $institusiId,
            'mode'         => 'chat:konsultan',
        ]);

        if ($outcome->failed()) {
            throw new \RuntimeException('Panggilan AI chat gagal: ' . ($outcome->result->error ?? 'tidak diketahui'));
        }

        return trim($outcome->text());
    }
}
