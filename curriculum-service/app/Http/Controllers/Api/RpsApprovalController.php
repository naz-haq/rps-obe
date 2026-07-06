<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\RpsApprovalLogResource;
use App\Http\Resources\RpsVersionResource;
use App\Models\RpsVersion;
use App\Services\Approval\Exceptions\ApprovalException;
use App\Services\Approval\RpsApprovalService;
use Illuminate\Http\Request;

/**
 * Modul 4 — Workflow Approval.
 * Antrian tinjauan + transisi status (ajukan/setujui/revisi/tarik) + riwayat.
 */
class RpsApprovalController extends Controller
{
    use AppliesSorting;

    public function __construct(private RpsApprovalService $approval) {}

    /** Antrian RPS menunggu tinjauan (default status=review). */
    public function antrian(Request $request)
    {
        $query = RpsVersion::query()->withCount(['minggu', 'komponenPenilaian']);

        $query->where('status', $request->string('status')->toString() ?: 'review');

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('kode_mk')) {
            $query->where('kode_mk', $request->string('kode_mk'));
        }

        $this->applySort($query, $request, ['kode_mk', 'versi', 'status', 'submitted_at', 'created_at'], 'submitted_at', 'desc');

        return RpsVersionResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    /** Riwayat transisi status (audit trail) satu RPS. */
    public function riwayat(RpsVersion $rpsVersion)
    {
        $logs = $rpsVersion->approvalLogs()->orderByDesc('id')->get();

        return RpsApprovalLogResource::collection($logs);
    }

    public function ajukan(Request $request, RpsVersion $rpsVersion)
    {
        return $this->jalankan(fn() => $this->approval->ajukan($rpsVersion, $this->aktor($request)));
    }

    public function setujui(Request $request, RpsVersion $rpsVersion)
    {
        return $this->jalankan(fn() => $this->approval->setujui($rpsVersion, $this->aktor($request)));
    }

    public function revisi(Request $request, RpsVersion $rpsVersion)
    {
        return $this->jalankan(fn() => $this->approval->mintaRevisi($rpsVersion, $this->aktor($request)));
    }

    public function tarik(Request $request, RpsVersion $rpsVersion)
    {
        return $this->jalankan(fn() => $this->approval->tarik($rpsVersion, $this->aktor($request)));
    }

    /** Kumpulkan info aktor dari request (auth ditunda → opsional). */
    private function aktor(Request $request): array
    {
        return [
            'id'      => $request->user()?->id ?? $request->integer('actor_id') ?: null,
            'nama'    => $request->string('actor_nama')->toString() ?: null,
            'catatan' => $request->string('catatan')->toString() ?: null,
        ];
    }

    /** Bungkus aksi service; ubah ApprovalException menjadi 422. */
    private function jalankan(callable $aksi)
    {
        try {
            $rps = $aksi();
        } catch (ApprovalException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new RpsVersionResource($rps->loadCount(['minggu', 'komponenPenilaian']));
    }
}
