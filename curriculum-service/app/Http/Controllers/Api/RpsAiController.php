<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenerateSession;
use App\Models\RpsVersion;
use App\Services\Rps\KurikulumChatService;
use App\Services\Rps\RpsAuditService;
use App\Services\Rps\RpsSnapshot;
use Illuminate\Http\Request;

/**
 * Modul 2 — Layanan AI di atas RPS: Audit Keselarasan Konstruktif (fitur #6) &
 * Chat Konsultan OBE (fitur #7). Keduanya sadar konteks RPS lewat RpsSnapshot.
 */
class RpsAiController extends Controller
{
    public function __construct(
        private RpsSnapshot $snapshot,
        private RpsAuditService $audit,
        private KurikulumChatService $chat,
    ) {}

    /** Audit draf sesi generate (sebelum commit). */
    public function auditSession(GenerateSession $generateSession)
    {
        $mk = $generateSession->mataKuliah;
        $snapshot = $this->snapshot->fromSession($generateSession);
        $hasil = $this->audit->audit($snapshot, $generateSession->institusi_id, $mk?->jenis_mk);

        return response()->json(['data' => $hasil]);
    }

    /** Audit RPS resmi (committed). */
    public function auditRpsVersion(RpsVersion $rpsVersion)
    {
        $snapshot = $this->snapshot->fromRpsVersion($rpsVersion);
        $hasil = $this->audit->audit($snapshot, $rpsVersion->institusi_id);

        return response()->json(['data' => $hasil]);
    }

    /** Chat konsultan; opsional sadar konteks sesi generate atau RPS resmi. */
    public function chat(Request $request)
    {
        $data = $request->validate([
            'institusi_id'        => ['required', 'integer'],
            'messages'            => ['required', 'array', 'min:1'],
            'messages.*.sender'   => ['required', 'string'],
            'messages.*.text'     => ['required', 'string'],
            'generate_session_id' => ['nullable', 'integer', 'exists:generate_session,id'],
            'rps_version_id'      => ['nullable', 'integer', 'exists:rps_version,id'],
        ]);

        $snapshot = null;
        if (! empty($data['generate_session_id'])) {
            $session = GenerateSession::findOrFail($data['generate_session_id']);
            $snapshot = $this->snapshot->fromSession($session);
        } elseif (! empty($data['rps_version_id'])) {
            $rps = RpsVersion::findOrFail($data['rps_version_id']);
            $snapshot = $this->snapshot->fromRpsVersion($rps);
        }

        $balasan = $this->chat->reply($data['institusi_id'], $data['messages'], $snapshot);

        return response()->json(['data' => ['text' => $balasan]]);
    }
}
