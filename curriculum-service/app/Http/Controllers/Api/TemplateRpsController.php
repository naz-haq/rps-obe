<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TemplateRps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Template/format dokumen RPS untuk cetak seragam. Admin mengunggah berkas
 * template (docx/xlsx/html/pdf); satu template dapat ditandai aktif sebagai
 * acuan ekstraksi cetak. institusi_id diturunkan dari input (default 1).
 */
class TemplateRpsController extends Controller
{
    /** Daftar template untuk satu institusi. */
    public function index(Request $request): JsonResponse
    {
        $institusiId = $request->integer('institusi_id', 1);

        $items = TemplateRps::where('institusi_id', $institusiId)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $items->map(fn($t) => $this->format($t))]);
    }

    /** Unggah template baru (multipart: berkas + nama + keterangan). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id' => ['nullable', 'integer'],
            'nama'         => ['required', 'string', 'max:255'],
            'keterangan'   => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['nullable', 'boolean'],
            'berkas'       => ['required', 'file', 'mimes:docx,doc,xlsx,xls,html,htm,pdf', 'max:20480'],
        ]);

        $institusiId = $data['institusi_id'] ?? 1;
        $file = $request->file('berkas');
        $path = $file->store("templates/{$institusiId}", 'local');

        $template = TemplateRps::create([
            'institusi_id'     => $institusiId,
            'nama'             => $data['nama'],
            'keterangan'       => $data['keterangan'] ?? null,
            'berkas_path'      => $path,
            'berkas_nama_asli' => $file->getClientOriginalName(),
            'format'           => strtolower($file->getClientOriginalExtension()),
            'struktur_kolom'   => [],
            'is_active'        => (bool) ($data['is_active'] ?? false),
        ]);

        if ($template->is_active) {
            $this->deactivateOthers($template);
        }

        return response()->json(['data' => $this->format($template)], 201);
    }

    /** Perbarui metadata (nama/keterangan) template. */
    public function update(Request $request, TemplateRps $template): JsonResponse
    {
        $data = $request->validate([
            'nama'       => ['sometimes', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $template->update($data);

        return response()->json(['data' => $this->format($template)]);
    }

    /** Tandai satu template sebagai aktif (nonaktifkan yang lain). */
    public function activate(TemplateRps $template): JsonResponse
    {
        $template->update(['is_active' => true]);
        $this->deactivateOthers($template);

        return response()->json(['data' => $this->format($template)]);
    }

    /** Hapus template + berkasnya. */
    public function destroy(TemplateRps $template): JsonResponse
    {
        if ($template->berkas_path) {
            Storage::disk('local')->delete($template->berkas_path);
        }
        $template->delete();

        return response()->json(['message' => 'Template dihapus.']);
    }

    /** Unduh berkas template. */
    public function download(TemplateRps $template): StreamedResponse
    {
        abort_unless($template->berkas_path && Storage::disk('local')->exists($template->berkas_path), 404);

        return Storage::disk('local')->download(
            $template->berkas_path,
            $template->berkas_nama_asli ?? "template-rps.{$template->format}",
        );
    }

    private function deactivateOthers(TemplateRps $template): void
    {
        TemplateRps::where('institusi_id', $template->institusi_id)
            ->where('id', '!=', $template->id)
            ->update(['is_active' => false]);
    }

    private function format(TemplateRps $t): array
    {
        return [
            'id'               => $t->id,
            'institusi_id'     => $t->institusi_id,
            'nama'             => $t->nama,
            'keterangan'       => $t->keterangan,
            'format'           => $t->format,
            'berkas_nama_asli' => $t->berkas_nama_asli,
            'is_active'        => (bool) $t->is_active,
            'created_at'       => $t->created_at?->toIso8601String(),
        ];
    }
}
