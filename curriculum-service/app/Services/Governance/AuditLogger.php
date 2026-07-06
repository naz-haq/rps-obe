<?php

namespace App\Services\Governance;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Modul 8 — Tata Kelola: pencatat audit trail terpadu.
 *
 * Menyimpan jejak "siapa mengubah apa, kapan" untuk kebutuhan akreditasi.
 * Dipanggil dari peristiwa penting (persetujuan RPS, finalisasi OBAEI, dsb).
 * Auth ditunda: user_id/actor_nama diterima opsional.
 */
class AuditLogger
{
    /**
     * Catat satu peristiwa audit.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function catat(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $meta = [],
        ?int $institusiId = null,
        ?int $userId = null,
        ?string $actorNama = null,
    ): AuditLog {
        if ($actorNama !== null) {
            $meta['actor_nama'] = $actorNama;
        }

        return AuditLog::create([
            'institusi_id' => $institusiId,
            'user_id'      => $userId,
            'action'       => $action,
            'entity'       => $entity,
            'entity_id'    => $entityId,
            'meta'         => $meta !== [] ? $meta : null,
            'created_at'   => now(),
        ]);
    }

    /** Ambil identitas aktor dari request (auth ditunda → fallback field manual). */
    public static function aktorDariRequest(Request $request): array
    {
        return [
            'user_id'    => $request->user()?->id ?? ($request->integer('actor_id') ?: null),
            'actor_nama' => $request->user()?->name ?? ($request->string('actor_nama')->toString() ?: null),
        ];
    }
}
