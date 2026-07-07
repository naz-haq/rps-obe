<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BahanKajianResource;
use App\Http\Resources\CplResource;
use App\Http\Resources\MataKuliahResource;
use App\Http\Resources\ProfilLulusanResource;
use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\CplBahanKajian;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkBahanKajian;
use App\Models\MkCpl;
use App\Models\PlCpl;
use App\Models\ProfilLulusan;
use App\Services\Ai\AiService;
use Illuminate\Http\Request;

/**
 * Peta kurikulum: matriks pemetaan MK x CPL (mk_cpl) + traceability.
 * institusi_id selalu diturunkan dari kurikulum (bukan dari input klien).
 */
class PetaKurikulumController extends Controller
{
    /** Matriks lengkap: daftar MK, daftar CPL, dan sel keterkaitan (mk_cpl). */
    public function matriks(Kurikulum $kurikulum)
    {
        $mk = $kurikulum->mataKuliah()->orderBy('semester')->orderBy('kode_mk')->get();
        $cpl = $kurikulum->cpl()->orderBy('kode')->get();

        $links = MkCpl::query()
            ->where('institusi_id', $kurikulum->institusi_id)
            ->whereIn('cpl_id', $cpl->pluck('id'))
            ->get(['kode_mk', 'cpl_id', 'bobot']);

        return response()->json([
            'data' => [
                'mata_kuliah' => MataKuliahResource::collection($mk),
                'cpl'         => CplResource::collection($cpl),
                'links'       => $links->map(fn($l) => [
                    'kode_mk' => $l->kode_mk,
                    'cpl_id'  => $l->cpl_id,
                    'bobot'   => $l->bobot,
                ]),
            ],
        ]);
    }

    /** Tautkan (upsert) satu sel matriks MK x CPL. */
    public function link(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'kode_mk' => ['required', 'string', 'max:255'],
            'cpl_id'  => ['required', 'integer'],
            'bobot'   => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->assertMilikKurikulum($kurikulum, $data['kode_mk'], (int) $data['cpl_id']);

        $link = MkCpl::updateOrCreate(
            ['institusi_id' => $kurikulum->institusi_id, 'kode_mk' => $data['kode_mk'], 'cpl_id' => $data['cpl_id']],
            ['bobot' => $data['bobot'] ?? null],
        );

        return response()->json(['data' => [
            'kode_mk' => $link->kode_mk,
            'cpl_id'  => $link->cpl_id,
            'bobot'   => $link->bobot,
        ]], $link->wasRecentlyCreated ? 201 : 200);
    }

    /** Putuskan satu sel matriks MK x CPL. */
    public function unlink(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'kode_mk' => ['required', 'string', 'max:255'],
            'cpl_id'  => ['required', 'integer'],
        ]);

        MkCpl::where('institusi_id', $kurikulum->institusi_id)
            ->where('kode_mk', $data['kode_mk'])
            ->where('cpl_id', $data['cpl_id'])
            ->delete();

        return response()->json(['message' => 'Tautan MK-CPL dihapus.']);
    }

    /**
     * Traceability: tiap CPL beserta MK yang mengembannya (deteksi CPL yatim
     * = tak diampu MK mana pun, tanda peta kurikulum belum lengkap).
     */
    public function traceability(Kurikulum $kurikulum)
    {
        $cpl = $kurikulum->cpl()->orderBy('kode')->get();
        $mk = $kurikulum->mataKuliah()->get(['kode_mk', 'nama']);
        $mkByKode = $mk->keyBy('kode_mk');

        $links = MkCpl::query()
            ->where('institusi_id', $kurikulum->institusi_id)
            ->whereIn('cpl_id', $cpl->pluck('id'))
            ->get(['kode_mk', 'cpl_id', 'bobot']);
        $byCpl = $links->groupBy('cpl_id');

        $peta = $cpl->map(function (Cpl $c) use ($byCpl, $mkByKode) {
            $daftar = ($byCpl[$c->id] ?? collect())
                ->filter(fn($l) => $mkByKode->has($l->kode_mk))
                ->map(fn($l) => [
                    'kode_mk' => $l->kode_mk,
                    'nama'    => $mkByKode[$l->kode_mk]->nama ?? null,
                    'bobot'   => $l->bobot,
                ])->values();

            return [
                'cpl_id'    => $c->id,
                'kode'      => $c->kode,
                'deskripsi' => $c->deskripsi,
                'mata_kuliah' => $daftar,
                'yatim'     => $daftar->isEmpty(),
            ];
        });

        return response()->json([
            'data' => [
                'peta'        => $peta,
                'cpl_yatim'   => $peta->where('yatim', true)->pluck('kode')->values(),
                'total_cpl'   => $cpl->count(),
                'total_mk'    => $mk->count(),
            ],
        ]);
    }

    /**
     * Matriks CPL x Bahan Kajian: daftar bahan kajian (baris), daftar CPL
     * (kolom), dan sel keterkaitan (cpl_bahan_kajian).
     */
    public function matriksBahanKajian(Kurikulum $kurikulum)
    {
        $bahanKajian = $kurikulum->bahanKajian()->orderBy('nama')->get();
        $cpl = $kurikulum->cpl()->orderBy('kode')->get();

        $links = CplBahanKajian::query()
            ->where('institusi_id', $kurikulum->institusi_id)
            ->whereIn('cpl_id', $cpl->pluck('id'))
            ->whereIn('bahan_kajian_id', $bahanKajian->pluck('id'))
            ->get(['cpl_id', 'bahan_kajian_id']);

        return response()->json([
            'data' => [
                'bahan_kajian' => BahanKajianResource::collection($bahanKajian),
                'cpl'          => CplResource::collection($cpl),
                'links'        => $links->map(fn($l) => [
                    'cpl_id'          => $l->cpl_id,
                    'bahan_kajian_id' => $l->bahan_kajian_id,
                ]),
            ],
        ]);
    }

    /** Tautkan (upsert) satu sel matriks CPL x Bahan Kajian. */
    public function linkBahanKajian(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'cpl_id'          => ['required', 'integer'],
            'bahan_kajian_id' => ['required', 'integer'],
        ]);

        $this->assertCplBahanKajian($kurikulum, (int) $data['cpl_id'], (int) $data['bahan_kajian_id']);

        $link = CplBahanKajian::firstOrCreate([
            'institusi_id'    => $kurikulum->institusi_id,
            'cpl_id'          => $data['cpl_id'],
            'bahan_kajian_id' => $data['bahan_kajian_id'],
        ]);

        return response()->json(['data' => [
            'cpl_id'          => $link->cpl_id,
            'bahan_kajian_id' => $link->bahan_kajian_id,
        ]], $link->wasRecentlyCreated ? 201 : 200);
    }

    /** Putuskan satu sel matriks CPL x Bahan Kajian. */
    public function unlinkBahanKajian(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'cpl_id'          => ['required', 'integer'],
            'bahan_kajian_id' => ['required', 'integer'],
        ]);

        CplBahanKajian::where('institusi_id', $kurikulum->institusi_id)
            ->where('cpl_id', $data['cpl_id'])
            ->where('bahan_kajian_id', $data['bahan_kajian_id'])
            ->delete();

        return response()->json(['message' => 'Tautan CPL-Bahan Kajian dihapus.']);
    }

    /**
     * Matriks Bahan Kajian x Mata Kuliah (KPT — pembentukan MK dari bahan kajian):
     * daftar mata kuliah (baris), daftar bahan kajian (kolom), sel keterkaitan
     * (mk_bahan_kajian). Acuan peninjauan struktur: bahan kajian mana dibungkus MK.
     */
    public function matriksMkBahanKajian(Kurikulum $kurikulum)
    {
        $mk = $kurikulum->mataKuliah()->orderBy('semester')->orderBy('kode_mk')->get();
        $bahanKajian = $kurikulum->bahanKajian()->orderBy('nama')->get();

        $links = MkBahanKajian::query()
            ->where('institusi_id', $kurikulum->institusi_id)
            ->whereIn('kode_mk', $mk->pluck('kode_mk'))
            ->whereIn('bahan_kajian_id', $bahanKajian->pluck('id'))
            ->get(['kode_mk', 'bahan_kajian_id']);

        return response()->json([
            'data' => [
                'mata_kuliah'  => MataKuliahResource::collection($mk),
                'bahan_kajian' => BahanKajianResource::collection($bahanKajian),
                'links'        => $links->map(fn($l) => [
                    'kode_mk'         => $l->kode_mk,
                    'bahan_kajian_id' => $l->bahan_kajian_id,
                ]),
            ],
        ]);
    }

    /** Tautkan (upsert) satu sel matriks Bahan Kajian x Mata Kuliah. */
    public function linkMkBahanKajian(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'kode_mk'         => ['required', 'string', 'max:255'],
            'bahan_kajian_id' => ['required', 'integer'],
        ]);

        $this->assertMkBahanKajian($kurikulum, $data['kode_mk'], (int) $data['bahan_kajian_id']);

        $link = MkBahanKajian::firstOrCreate([
            'institusi_id'    => $kurikulum->institusi_id,
            'kode_mk'         => $data['kode_mk'],
            'bahan_kajian_id' => $data['bahan_kajian_id'],
        ]);

        return response()->json(['data' => [
            'kode_mk'         => $link->kode_mk,
            'bahan_kajian_id' => $link->bahan_kajian_id,
        ]], $link->wasRecentlyCreated ? 201 : 200);
    }

    /** Putuskan satu sel matriks Bahan Kajian x Mata Kuliah. */
    public function unlinkMkBahanKajian(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'kode_mk'         => ['required', 'string', 'max:255'],
            'bahan_kajian_id' => ['required', 'integer'],
        ]);

        MkBahanKajian::where('institusi_id', $kurikulum->institusi_id)
            ->where('kode_mk', $data['kode_mk'])
            ->where('bahan_kajian_id', $data['bahan_kajian_id'])
            ->delete();

        return response()->json(['message' => 'Tautan Bahan Kajian-Mata Kuliah dihapus.']);
    }

    /**
     * Matriks Profil Lulusan x CPL: daftar profil lulusan (baris), daftar CPL
     * (kolom), dan sel keterkaitan (pl_cpl).
     */
    public function matriksProfilLulusan(Kurikulum $kurikulum)
    {
        $profil = $kurikulum->profilLulusan()->orderBy('kode')->get();
        $cpl = $kurikulum->cpl()->orderBy('kode')->get();

        $links = PlCpl::query()
            ->where('institusi_id', $kurikulum->institusi_id)
            ->whereIn('profil_lulusan_id', $profil->pluck('id'))
            ->whereIn('cpl_id', $cpl->pluck('id'))
            ->get(['profil_lulusan_id', 'cpl_id']);

        return response()->json([
            'data' => [
                'profil_lulusan' => ProfilLulusanResource::collection($profil),
                'cpl'            => CplResource::collection($cpl),
                'links'          => $links->map(fn($l) => [
                    'profil_lulusan_id' => $l->profil_lulusan_id,
                    'cpl_id'            => $l->cpl_id,
                ]),
            ],
        ]);
    }

    /** Tautkan (upsert) satu sel matriks Profil Lulusan x CPL. */
    public function linkProfilLulusan(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'profil_lulusan_id' => ['required', 'integer'],
            'cpl_id'            => ['required', 'integer'],
        ]);

        $this->assertPlCpl($kurikulum, (int) $data['profil_lulusan_id'], (int) $data['cpl_id']);

        $link = PlCpl::firstOrCreate([
            'institusi_id'      => $kurikulum->institusi_id,
            'profil_lulusan_id' => $data['profil_lulusan_id'],
            'cpl_id'            => $data['cpl_id'],
        ]);

        return response()->json(['data' => [
            'profil_lulusan_id' => $link->profil_lulusan_id,
            'cpl_id'            => $link->cpl_id,
        ]], $link->wasRecentlyCreated ? 201 : 200);
    }

    /** Putuskan satu sel matriks Profil Lulusan x CPL. */
    public function unlinkProfilLulusan(Request $request, Kurikulum $kurikulum)
    {
        $data = $request->validate([
            'profil_lulusan_id' => ['required', 'integer'],
            'cpl_id'            => ['required', 'integer'],
        ]);

        PlCpl::where('institusi_id', $kurikulum->institusi_id)
            ->where('profil_lulusan_id', $data['profil_lulusan_id'])
            ->where('cpl_id', $data['cpl_id'])
            ->delete();

        return response()->json(['message' => 'Tautan PL-CPL dihapus.']);
    }

    /**
     * Saran AI: petakan CPL yang mendukung tiap Profil Lulusan.
     * Mengembalikan usulan tautan (belum disimpan) untuk ditinjau di UI.
     */
    public function suggestProfilLulusan(Kurikulum $kurikulum, AiService $ai)
    {
        $profil = $kurikulum->profilLulusan()->orderBy('kode')->get(['id', 'kode', 'deskripsi']);
        $cpl = $kurikulum->cpl()->orderBy('kode')->get(['id', 'kode', 'deskripsi']);
        if ($profil->isEmpty() || $cpl->isEmpty()) {
            return response()->json(['data' => ['links' => []]]);
        }

        $system = 'Anda ahli pemetaan kurikulum OBE. Petakan CPL yang paling relevan mendukung tiap Profil Lulusan. '
            . 'Gunakan HANYA kode yang tersedia; jangan mengarang kode. Satu PL boleh didukung beberapa CPL.';
        $prompt = "PROFIL LULUSAN:\n" . json_encode($profil->map(fn($p) => ['kode' => $p->kode, 'deskripsi' => $p->deskripsi]), JSON_UNESCAPED_UNICODE)
            . "\n\nCPL:\n" . json_encode($cpl->map(fn($c) => ['kode' => $c->kode, 'deskripsi' => $c->deskripsi]), JSON_UNESCAPED_UNICODE)
            . "\n\nBalas HANYA JSON: {\"tautan\":[{\"profil_lulusan_kode\":\"..\",\"cpl_kode\":\"..\"}]}";

        $data = $this->runSuggest($ai, $kurikulum, $system, $prompt);

        $plByKode = $profil->keyBy('kode');
        $cplByKode = $cpl->keyBy('kode');
        $links = [];
        foreach ($data['tautan'] ?? [] as $t) {
            $pl = $plByKode[$t['profil_lulusan_kode'] ?? ''] ?? null;
            $c = $cplByKode[$t['cpl_kode'] ?? ''] ?? null;
            if ($pl && $c) {
                $links[] = ['profil_lulusan_id' => $pl->id, 'cpl_id' => $c->id];
            }
        }

        return response()->json(['data' => ['links' => array_values($links)]]);
    }

    /**
     * Saran AI: petakan bahan kajian yang menopang tiap CPL.
     * Mengembalikan usulan tautan (belum disimpan) untuk ditinjau di UI.
     */
    public function suggestBahanKajian(Kurikulum $kurikulum, AiService $ai)
    {
        $bahanKajian = $kurikulum->bahanKajian()->orderBy('nama')->get(['id', 'nama', 'deskripsi']);
        $cpl = $kurikulum->cpl()->orderBy('kode')->get(['id', 'kode', 'deskripsi']);
        if ($bahanKajian->isEmpty() || $cpl->isEmpty()) {
            return response()->json(['data' => ['links' => []]]);
        }

        $system = 'Anda ahli pemetaan kurikulum OBE. Petakan bahan kajian yang paling relevan menopang tiap CPL. '
            . 'Gunakan HANYA nama bahan kajian & kode CPL yang tersedia; jangan mengarang. Satu CPL boleh ditopang beberapa bahan kajian.';
        $prompt = "CPL:\n" . json_encode($cpl->map(fn($c) => ['kode' => $c->kode, 'deskripsi' => $c->deskripsi]), JSON_UNESCAPED_UNICODE)
            . "\n\nBAHAN KAJIAN:\n" . json_encode($bahanKajian->map(fn($b) => ['nama' => $b->nama, 'deskripsi' => $b->deskripsi]), JSON_UNESCAPED_UNICODE)
            . "\n\nBalas HANYA JSON: {\"tautan\":[{\"cpl_kode\":\"..\",\"bahan_kajian_nama\":\"..\"}]}";

        $data = $this->runSuggest($ai, $kurikulum, $system, $prompt);

        $cplByKode = $cpl->keyBy('kode');
        $bkByNama = $bahanKajian->keyBy('nama');
        $links = [];
        foreach ($data['tautan'] ?? [] as $t) {
            $c = $cplByKode[$t['cpl_kode'] ?? ''] ?? null;
            $bk = $bkByNama[$t['bahan_kajian_nama'] ?? ''] ?? null;
            if ($c && $bk) {
                $links[] = ['cpl_id' => $c->id, 'bahan_kajian_id' => $bk->id];
            }
        }

        return response()->json(['data' => ['links' => array_values($links)]]);
    }

    /**
     * Saran AI: petakan CPL yang diampu tiap mata kuliah (matriks CPL x MK).
     * Mengembalikan usulan tautan (belum disimpan) untuk ditinjau di UI.
     */
    public function suggestMataKuliah(Kurikulum $kurikulum, AiService $ai)
    {
        $mk = $kurikulum->mataKuliah()->orderBy('kode_mk')->get(['kode_mk', 'nama', 'deskripsi_singkat']);
        $cpl = $kurikulum->cpl()->orderBy('kode')->get(['id', 'kode', 'deskripsi']);
        if ($mk->isEmpty() || $cpl->isEmpty()) {
            return response()->json(['data' => ['links' => []]]);
        }

        $system = 'Anda ahli pemetaan kurikulum OBE. Petakan CPL yang paling relevan diampu (dibebankan) tiap mata kuliah. '
            . 'Gunakan HANYA kode mata kuliah & kode CPL yang tersedia; jangan mengarang. Satu mata kuliah boleh mengampu beberapa CPL.';
        $prompt = "MATA KULIAH:\n" . json_encode($mk->map(fn($m) => ['kode' => $m->kode_mk, 'nama' => $m->nama, 'deskripsi' => $m->deskripsi_singkat]), JSON_UNESCAPED_UNICODE)
            . "\n\nCPL:\n" . json_encode($cpl->map(fn($c) => ['kode' => $c->kode, 'deskripsi' => $c->deskripsi]), JSON_UNESCAPED_UNICODE)
            . "\n\nBalas HANYA JSON: {\"tautan\":[{\"mata_kuliah_kode\":\"..\",\"cpl_kode\":\"..\"}]}";

        $data = $this->runSuggest($ai, $kurikulum, $system, $prompt);

        $mkByKode = $mk->keyBy('kode_mk');
        $cplByKode = $cpl->keyBy('kode');
        $links = [];
        foreach ($data['tautan'] ?? [] as $t) {
            $m = $mkByKode[$t['mata_kuliah_kode'] ?? ''] ?? null;
            $c = $cplByKode[$t['cpl_kode'] ?? ''] ?? null;
            if ($m && $c) {
                $links[] = ['kode_mk' => $m->kode_mk, 'cpl_id' => $c->id];
            }
        }

        return response()->json(['data' => ['links' => array_values($links)]]);
    }

    /**
     * Saran AI: petakan bahan kajian yang dibungkus tiap mata kuliah (matriks BK x MK).
     * Mengembalikan usulan tautan (belum disimpan) untuk ditinjau di UI.
     */
    public function suggestMkBahanKajian(Kurikulum $kurikulum, AiService $ai)
    {
        $mk = $kurikulum->mataKuliah()->orderBy('kode_mk')->get(['kode_mk', 'nama', 'deskripsi_singkat']);
        $bahanKajian = $kurikulum->bahanKajian()->orderBy('nama')->get(['id', 'nama', 'deskripsi']);
        if ($mk->isEmpty() || $bahanKajian->isEmpty()) {
            return response()->json(['data' => ['links' => []]]);
        }

        $system = 'Anda ahli pemetaan kurikulum OBE/KPT. Petakan bahan kajian yang paling relevan dibungkus (dimuat) tiap mata kuliah. '
            . 'Gunakan HANYA kode mata kuliah & nama bahan kajian yang tersedia; jangan mengarang. Satu mata kuliah boleh memuat beberapa bahan kajian.';
        $prompt = "MATA KULIAH:\n" . json_encode($mk->map(fn($m) => ['kode' => $m->kode_mk, 'nama' => $m->nama, 'deskripsi' => $m->deskripsi_singkat]), JSON_UNESCAPED_UNICODE)
            . "\n\nBAHAN KAJIAN:\n" . json_encode($bahanKajian->map(fn($b) => ['nama' => $b->nama, 'deskripsi' => $b->deskripsi]), JSON_UNESCAPED_UNICODE)
            . "\n\nBalas HANYA JSON: {\"tautan\":[{\"mata_kuliah_kode\":\"..\",\"bahan_kajian_nama\":\"..\"}]}";

        $data = $this->runSuggest($ai, $kurikulum, $system, $prompt);

        $mkByKode = $mk->keyBy('kode_mk');
        $bkByNama = $bahanKajian->keyBy('nama');
        $links = [];
        foreach ($data['tautan'] ?? [] as $t) {
            $m = $mkByKode[$t['mata_kuliah_kode'] ?? ''] ?? null;
            $bk = $bkByNama[$t['bahan_kajian_nama'] ?? ''] ?? null;
            if ($m && $bk) {
                $links[] = ['kode_mk' => $m->kode_mk, 'bahan_kajian_id' => $bk->id];
            }
        }

        return response()->json(['data' => ['links' => array_values($links)]]);
    }

    /** Jalankan AI generate + parse JSON; 503 bila gagal. */
    private function runSuggest(AiService $ai, Kurikulum $kurikulum, string $system, string $prompt): array
    {
        // Matriks besar (mis. 100+ mata kuliah) menghasilkan JSON tautan yang
        // panjang. Default 'generate' (4000) tidak cukup dan bikin keluaran
        // terpotong (finish_reason 'length') sehingga JSON gagal di-parse dan
        // saran kosong. Beri anggaran token lebih besar khusus penyaranan.
        $outcome = $ai->run('generate', $system, $prompt, [
            'institusi_id' => $kurikulum->institusi_id,
            'max_tokens' => 8000,
        ]);
        if ($outcome->failed()) {
            abort(503, 'Layanan AI sedang sibuk. Coba lagi.');
        }

        $clean = trim($outcome->text());
        $clean = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean));
        $data = json_decode($clean, true);
        if (! is_array($data)) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $data = json_decode(substr($clean, $start, $end - $start + 1), true);
            }
        }

        return is_array($data) ? $data : [];
    }

    /** Pastikan kode_mk & cpl_id benar-benar milik kurikulum ini. */
    private function assertMilikKurikulum(Kurikulum $kurikulum, string $kodeMk, int $cplId): void
    {
        $mkValid = MataKuliah::where('kurikulum_id', $kurikulum->id)->where('kode_mk', $kodeMk)->exists();
        abort_unless($mkValid, 422, "Mata kuliah '{$kodeMk}' bukan bagian kurikulum ini.");

        $cplValid = Cpl::where('kurikulum_id', $kurikulum->id)->where('id', $cplId)->exists();
        abort_unless($cplValid, 422, 'CPL bukan bagian kurikulum ini.');
    }

    /** Pastikan cpl_id & bahan_kajian_id benar-benar milik kurikulum ini. */
    private function assertCplBahanKajian(Kurikulum $kurikulum, int $cplId, int $bahanKajianId): void
    {
        $cplValid = Cpl::where('kurikulum_id', $kurikulum->id)->where('id', $cplId)->exists();
        abort_unless($cplValid, 422, 'CPL bukan bagian kurikulum ini.');

        $bkValid = BahanKajian::where('kurikulum_id', $kurikulum->id)->where('id', $bahanKajianId)->exists();
        abort_unless($bkValid, 422, 'Bahan kajian bukan bagian kurikulum ini.');
    }

    /** Pastikan kode_mk & bahan_kajian_id benar-benar milik kurikulum ini. */
    private function assertMkBahanKajian(Kurikulum $kurikulum, string $kodeMk, int $bahanKajianId): void
    {
        $mkValid = MataKuliah::where('kurikulum_id', $kurikulum->id)->where('kode_mk', $kodeMk)->exists();
        abort_unless($mkValid, 422, "Mata kuliah '{$kodeMk}' bukan bagian kurikulum ini.");

        $bkValid = BahanKajian::where('kurikulum_id', $kurikulum->id)->where('id', $bahanKajianId)->exists();
        abort_unless($bkValid, 422, 'Bahan kajian bukan bagian kurikulum ini.');
    }

    /** Pastikan profil_lulusan_id & cpl_id benar-benar milik kurikulum ini. */
    private function assertPlCpl(Kurikulum $kurikulum, int $profilLulusanId, int $cplId): void
    {
        $plValid = ProfilLulusan::where('kurikulum_id', $kurikulum->id)->where('id', $profilLulusanId)->exists();
        abort_unless($plValid, 422, 'Profil lulusan bukan bagian kurikulum ini.');

        $cplValid = Cpl::where('kurikulum_id', $kurikulum->id)->where('id', $cplId)->exists();
        abort_unless($cplValid, 422, 'CPL bukan bagian kurikulum ini.');
    }
}
