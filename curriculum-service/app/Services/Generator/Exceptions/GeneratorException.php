<?php

namespace App\Services\Generator\Exceptions;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Dilempar saat pelanggaran aturan pipeline generator: tahap tak dikenal,
 * prasyarat tahap belum disetujui, tahap terkunci, atau keluaran AI bukan
 * JSON valid.
 */
class GeneratorException extends RuntimeException
{
    /**
     * Pesan ramah pengguna: error teknis (kontrak/panggilan AI) diberi label
     * instruktif; pesan aturan bisnis yang sudah jelas diteruskan apa adanya.
     */
    public function userMessage(): string
    {
        $msg = $this->getMessage();

        if (str_starts_with($msg, 'Kontrak generator:') || str_starts_with($msg, 'Penempatan evaluasi')) {
            return 'Error A — Keluaran AI belum sesuai aturan format RPS. '
                . 'Silakan muat ulang halaman lalu jalankan Generate ulang pada tahap ini. '
                . 'Bila berulang, coba model AI lain di Pengaturan AI.';
        }
        if (str_starts_with($msg, 'Panggilan AI gagal')) {
            return 'Error B — Layanan AI tidak merespons atau gagal. '
                . 'Coba lagi beberapa saat lagi; bila berulang, periksa kunci API dan saldo provider di Pengaturan AI.';
        }
        if (
            str_starts_with($msg, 'Tidak ada kandidat yang memenuhi kontrak')
            || str_starts_with($msg, 'Tidak ada usulan yang memenuhi kontrak')
            || str_starts_with($msg, 'AI harus mengembalikan tepat satu item')
            || str_starts_with($msg, 'Item usulan tidak valid')
            || str_starts_with($msg, 'AI tidak mengembalikan rincian pertemuan')
            || str_starts_with($msg, 'Rincian pertemuan dari AI tidak cocok')
        ) {
            return 'Error C — AI belum menghasilkan usulan yang valid. '
                . 'Silakan ulangi permintaan; bila berulang, perjelas instruksi atau coba model AI lain.';
        }

        return $msg;
    }

    /** Payload respons API: pesan ramah untuk UI, detail teknis masuk log. */
    public function responsePayload(): array
    {
        $friendly = $this->userMessage();
        if ($friendly === $this->getMessage()) {
            return ['message' => $friendly];
        }

        Log::warning('Generator error (detail teknis)', ['detail' => $this->getMessage()]);

        return ['message' => $friendly];
    }
}
