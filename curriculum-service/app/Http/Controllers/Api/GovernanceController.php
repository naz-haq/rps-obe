<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\NotifikasiResource;
use App\Models\AiInteraksi;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

/**
 * Modul 8 — Tata Kelola & Monitoring.
 *
 * Menyajikan dashboard biaya/penggunaan AI (dari ai_interaksi), audit log viewer
 * (jejak "siapa ubah apa kapan"), dan notifikasi. Filter opsional per tenant + periode.
 */
class GovernanceController extends Controller
{
    use AppliesSorting;

    /** KPI ringkas dashboard tata kelola. */
    public function ringkasan(Request $request)
    {
        $request->validate([
            'institusi_id' => ['nullable', 'integer', 'exists:institusi,id'],
            'hari'         => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $hari = $request->integer('hari', 30);
        $sejak = now()->subDays($hari)->startOfDay();
        $institusiId = $request->filled('institusi_id') ? $request->integer('institusi_id') : null;

        $interaksi = AiInteraksi::query()
            ->when($institusiId, fn($q) => $q->where('institusi_id', $institusiId))
            ->where('created_at', '>=', $sejak);

        $total = (clone $interaksi)->count();
        $sukses = (clone $interaksi)->where('status', 'sukses')->count();

        $audit = AuditLog::query()
            ->when($institusiId, fn($q) => $q->where('institusi_id', $institusiId))
            ->where('created_at', '>=', $sejak);

        $notifikasiUnread = Notifikasi::query()
            ->when($institusiId, fn($q) => $q->where('institusi_id', $institusiId))
            ->where('status', 'unread')
            ->count();

        return response()->json([
            'data' => [
                'periode_hari'    => $hari,
                'total_interaksi' => $total,
                'sukses'          => $sukses,
                'gagal'           => $total - $sukses,
                'success_rate'    => $total > 0 ? round($sukses / $total * 100, 1) : 0,
                'tokens_in'       => (int) (clone $interaksi)->sum('tokens_in'),
                'tokens_out'      => (int) (clone $interaksi)->sum('tokens_out'),
                'tokens_total'    => (int) ((clone $interaksi)->sum('tokens_in') + (clone $interaksi)->sum('tokens_out')),
                'total_biaya'     => round((float) (clone $interaksi)->sum('biaya'), 6),
                'total_audit'     => (clone $audit)->count(),
                'notifikasi_unread' => $notifikasiUnread,
            ],
        ]);
    }

    /** Rincian penggunaan untuk grafik (per mode / model / hari / status). */
    public function penggunaan(Request $request)
    {
        $request->validate([
            'institusi_id' => ['nullable', 'integer', 'exists:institusi,id'],
            'hari'         => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $hari = $request->integer('hari', 30);
        $sejak = now()->subDays($hari)->startOfDay();
        $institusiId = $request->filled('institusi_id') ? $request->integer('institusi_id') : null;

        $base = fn() => AiInteraksi::query()
            ->when($institusiId, fn($q) => $q->where('institusi_id', $institusiId))
            ->where('created_at', '>=', $sejak);

        $perMode = $base()
            ->selectRaw('mode, COUNT(*) AS jumlah, SUM(tokens_in + tokens_out) AS tokens, SUM(biaya) AS biaya')
            ->groupBy('mode')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn($r) => [
                'mode'   => $r->mode,
                'jumlah' => (int) $r->jumlah,
                'tokens' => (int) $r->tokens,
                'biaya'  => round((float) $r->biaya, 6),
            ]);

        $perModel = $base()
            ->selectRaw('model, COUNT(*) AS jumlah, SUM(tokens_in + tokens_out) AS tokens, SUM(biaya) AS biaya')
            ->groupBy('model')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn($r) => [
                'model'  => $r->model,
                'jumlah' => (int) $r->jumlah,
                'tokens' => (int) $r->tokens,
                'biaya'  => round((float) $r->biaya, 6),
            ]);

        $perHari = $base()
            ->selectRaw('DATE(created_at) AS tanggal, COUNT(*) AS jumlah, SUM(tokens_in + tokens_out) AS tokens, SUM(biaya) AS biaya')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->map(fn($r) => [
                'tanggal' => (string) $r->tanggal,
                'jumlah'  => (int) $r->jumlah,
                'tokens'  => (int) $r->tokens,
                'biaya'   => round((float) $r->biaya, 6),
            ]);

        $perStatus = $base()
            ->selectRaw('status, COUNT(*) AS jumlah')
            ->groupBy('status')
            ->get()
            ->map(fn($r) => ['status' => $r->status, 'jumlah' => (int) $r->jumlah]);

        return response()->json([
            'data' => [
                'per_mode'   => $perMode,
                'per_model'  => $perModel,
                'per_hari'   => $perHari,
                'per_status' => $perStatus,
            ],
        ]);
    }

    /** Audit log viewer (jejak perubahan untuk akreditasi). */
    public function auditLog(Request $request)
    {
        $query = AuditLog::query();

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('entity')) {
            $query->where('entity', $request->string('entity'));
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->string('action') . '%');
        }
        if ($request->filled('q')) {
            $q = (string) $request->string('q');
            $query->where(fn($w) => $w
                ->where('action', 'like', "%{$q}%")
                ->orWhere('entity', 'like', "%{$q}%"));
        }

        $this->applySort($query, $request, ['action', 'entity', 'created_at'], 'created_at', 'desc');

        return AuditLogResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    /** Daftar notifikasi. */
    public function notifikasi(Request $request)
    {
        $query = Notifikasi::query();

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $this->applySort($query, $request, ['jenis', 'status', 'created_at'], 'created_at', 'desc');

        return NotifikasiResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    /** Tandai notifikasi sudah dibaca. */
    public function tandaiDibaca(Notifikasi $notifikasi)
    {
        $notifikasi->update(['status' => 'read']);

        return new NotifikasiResource($notifikasi);
    }
}
