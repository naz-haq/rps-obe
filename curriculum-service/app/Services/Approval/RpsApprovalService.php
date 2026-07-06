<?php

namespace App\Services\Approval;

use App\Models\RpsApprovalLog;
use App\Models\RpsVersion;
use App\Models\Notifikasi;
use App\Services\Approval\Exceptions\ApprovalException;
use App\Services\Governance\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Modul 4 — Workflow Approval (Blueprint 6, Fase 2).
 *
 * Alur: Dosen menyusun (draft) → ajukan (review) → Kaprodi/STPMP setujui
 * (approved, terkunci) atau minta revisi (revisi, dosen suntai lagi).
 * Setiap transisi dicatat ke rps_approval_log sebagai audit trail.
 *
 * Auth ditunda: actor_id/actor_nama diterima opsional dari request.
 */
class RpsApprovalService
{
    /** Peta transisi yang diizinkan: aksi => [status asal yang valid]. */
    private const TRANSISI = [
        'ajukan'  => ['draft', 'revisi'],
        'setujui' => ['review'],
        'revisi'  => ['review'],
        'tarik'   => ['review'],
    ];

    /** Ajukan RPS untuk ditinjau (draft/revisi → review). */
    public function ajukan(RpsVersion $rps, array $aktor = []): RpsVersion
    {
        $this->pastikanBoleh('ajukan', $rps->status);

        return DB::transaction(function () use ($rps, $aktor) {
            $dari = $rps->status;
            $rps->forceFill([
                'status'       => 'review',
                'submitted_at' => now(),
                'created_by'   => $rps->created_by ?? ($aktor['id'] ?? null),
            ])->save();

            $this->catat($rps, 'ajukan', $dari, 'review', $aktor['catatan'] ?? null, $aktor);

            return $rps;
        });
    }

    /** Setujui RPS (review → approved, versi terkunci). */
    public function setujui(RpsVersion $rps, array $aktor = []): RpsVersion
    {
        $this->pastikanBoleh('setujui', $rps->status);

        return DB::transaction(function () use ($rps, $aktor) {
            $dari = $rps->status;
            $rps->forceFill([
                'status'         => 'approved',
                'approved_by'    => $aktor['id'] ?? null,
                'approved_at'    => now(),
                'catatan_review' => $aktor['catatan'] ?? null,
            ])->save();

            $this->catat($rps, 'setujui', $dari, 'approved', $aktor['catatan'] ?? null, $aktor);

            return $rps;
        });
    }

    /** Minta revisi (review → revisi). Catatan wajib agar dosen tahu perbaikannya. */
    public function mintaRevisi(RpsVersion $rps, array $aktor = []): RpsVersion
    {
        $this->pastikanBoleh('revisi', $rps->status);

        $catatan = trim((string) ($aktor['catatan'] ?? ''));
        if ($catatan === '') {
            throw new ApprovalException('Catatan revisi wajib diisi agar penyusun tahu bagian yang perlu diperbaiki.');
        }

        return DB::transaction(function () use ($rps, $aktor, $catatan) {
            $dari = $rps->status;
            $rps->forceFill([
                'status'         => 'revisi',
                'catatan_review' => $catatan,
            ])->save();

            $this->catat($rps, 'revisi', $dari, 'revisi', $catatan, $aktor);

            return $rps;
        });
    }

    /** Tarik pengajuan (review → draft) oleh penyusun. */
    public function tarik(RpsVersion $rps, array $aktor = []): RpsVersion
    {
        $this->pastikanBoleh('tarik', $rps->status);

        return DB::transaction(function () use ($rps, $aktor) {
            $dari = $rps->status;
            $rps->forceFill([
                'status'       => 'draft',
                'submitted_at' => null,
            ])->save();

            $this->catat($rps, 'tarik', $dari, 'draft', $aktor['catatan'] ?? null, $aktor);

            return $rps;
        });
    }

    private function pastikanBoleh(string $aksi, string $statusSekarang): void
    {
        $valid = self::TRANSISI[$aksi] ?? [];
        if (! in_array($statusSekarang, $valid, true)) {
            throw new ApprovalException(
                "Aksi '{$aksi}' tidak dapat dilakukan pada RPS berstatus '{$statusSekarang}'."
            );
        }
    }

    private function catat(RpsVersion $rps, string $aksi, ?string $dari, string $ke, ?string $catatan, array $aktor): void
    {
        RpsApprovalLog::create([
            'institusi_id'   => $rps->institusi_id,
            'rps_version_id' => $rps->id,
            'aksi'           => $aksi,
            'dari_status'    => $dari,
            'ke_status'      => $ke,
            'catatan'        => $catatan,
            'actor_id'       => $aktor['id'] ?? null,
            'actor_nama'     => $aktor['nama'] ?? null,
        ]);

        // Jejak audit terpadu untuk Modul 8 (Tata Kelola).
        AuditLogger::catat(
            action: "rps.{$aksi}",
            entity: 'RpsVersion',
            entityId: $rps->id,
            meta: ['dari' => $dari, 'ke' => $ke, 'catatan' => $catatan, 'kode_mk' => $rps->kode_mk ?? null],
            institusiId: $rps->institusi_id,
            userId: $aktor['id'] ?? null,
            actorNama: $aktor['nama'] ?? null,
        );

        // Notifikasi ke penyusun saat keputusan Kaprodi/STPMP keluar.
        if (in_array($aksi, ['setujui', 'revisi'], true) && $rps->created_by) {
            $pesan = $aksi === 'setujui'
                ? "RPS {$rps->kode_mk} versi {$rps->versi} telah disetujui."
                : "RPS {$rps->kode_mk} versi {$rps->versi} diminta revisi" . ($catatan ? ": {$catatan}" : ".");

            Notifikasi::create([
                'institusi_id' => $rps->institusi_id,
                'user_id'      => $rps->created_by,
                'jenis'        => $aksi === 'setujui' ? 'rps_disetujui' : 'rps_revisi',
                'konten'       => $pesan,
                'status'       => 'unread',
            ]);
        }
    }
}
