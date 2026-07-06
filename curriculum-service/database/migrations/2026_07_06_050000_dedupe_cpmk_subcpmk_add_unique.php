<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bersihkan CPMK & Sub-CPMK duplikat (akibat commit generator berulang yang
 * memakai create() tanpa cek keberadaan), lalu pasang UNIQUE index sebagai
 * pencegahan permanen.
 *
 * Strategi dedupe: pilih record kanonik (MIN id) per kunci natural, re-point
 * seluruh referensi FK ke record kanonik, baru hapus duplikatnya. Tabel dengan
 * nullOnDelete di-re-point agar tidak kehilangan tautan; indikator (cascade)
 * dipindah lalu di-dedupe berdasarkan teks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // FASE 1: Dedupe CPMK per (institusi_id, kode_mk, kode)
        // ------------------------------------------------------------------
        $cpmkGroups = DB::table('cpmk')
            ->select('institusi_id', 'kode_mk', 'kode', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as n'))
            ->groupBy('institusi_id', 'kode_mk', 'kode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($cpmkGroups as $g) {
            $keep = (int) $g->keep_id;
            $dupIds = DB::table('cpmk')
                ->where('institusi_id', $g->institusi_id)
                ->where('kode_mk', $g->kode_mk)
                ->where('kode', $g->kode)
                ->where('id', '!=', $keep)
                ->pluck('id')
                ->all();
            if (empty($dupIds)) {
                continue;
            }

            // Sub-CPMK & capaian_mahasiswa: pindah ke CPMK kanonik.
            DB::table('sub_cpmk')->whereIn('cpmk_id', $dupIds)->update(['cpmk_id' => $keep]);
            DB::table('capaian_mahasiswa')->whereIn('cpmk_id', $dupIds)->update(['cpmk_id' => $keep]);

            // cpmk_cpl: pindah bila belum ada pasangan (cpmk_id, cpl_id) di kanonik, selebihnya hapus.
            $dupCplRows = DB::table('cpmk_cpl')->whereIn('cpmk_id', $dupIds)->get();
            foreach ($dupCplRows as $row) {
                $exists = DB::table('cpmk_cpl')
                    ->where('cpmk_id', $keep)
                    ->where('cpl_id', $row->cpl_id)
                    ->exists();
                if ($exists) {
                    DB::table('cpmk_cpl')->where('id', $row->id)->delete();
                } else {
                    DB::table('cpmk_cpl')->where('id', $row->id)->update(['cpmk_id' => $keep]);
                }
            }

            DB::table('cpmk')->whereIn('id', $dupIds)->delete();
        }

        // ------------------------------------------------------------------
        // FASE 2: Dedupe Sub-CPMK per (institusi_id, cpmk_id, kode)
        // ------------------------------------------------------------------
        $subGroups = DB::table('sub_cpmk')
            ->select('institusi_id', 'cpmk_id', 'kode', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as n'))
            ->groupBy('institusi_id', 'cpmk_id', 'kode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($subGroups as $g) {
            $keep = (int) $g->keep_id;
            $dupIds = DB::table('sub_cpmk')
                ->where('institusi_id', $g->institusi_id)
                ->where('cpmk_id', $g->cpmk_id)
                ->where('kode', $g->kode)
                ->where('id', '!=', $keep)
                ->pluck('id')
                ->all();
            if (empty($dupIds)) {
                continue;
            }

            // Re-point seluruh referensi (nullOnDelete) ke Sub-CPMK kanonik.
            DB::table('rps_minggu')->whereIn('sub_cpmk_id', $dupIds)->update(['sub_cpmk_id' => $keep]);
            DB::table('komponen_penilaian')->whereIn('sub_cpmk_id', $dupIds)->update(['sub_cpmk_id' => $keep]);
            DB::table('capaian_mahasiswa')->whereIn('sub_cpmk_id', $dupIds)->update(['sub_cpmk_id' => $keep]);
            DB::table('tindak_lanjut')->whereIn('sub_cpmk_id', $dupIds)->update(['sub_cpmk_id' => $keep]);

            // Indikator (cascade): pindah ke kanonik lalu buang teks kembar.
            DB::table('indikator')->whereIn('sub_cpmk_id', $dupIds)->update(['sub_cpmk_id' => $keep]);
            $seen = [];
            $indikators = DB::table('indikator')->where('sub_cpmk_id', $keep)->orderBy('id')->get();
            foreach ($indikators as $ind) {
                $key = trim((string) $ind->deskripsi);
                if (isset($seen[$key])) {
                    DB::table('indikator')->where('id', $ind->id)->delete();
                } else {
                    $seen[$key] = true;
                }
            }

            DB::table('sub_cpmk')->whereIn('id', $dupIds)->delete();
        }

        // ------------------------------------------------------------------
        // PENCEGAHAN: UNIQUE index kunci natural
        // ------------------------------------------------------------------
        Schema::table('cpmk', function (Blueprint $table) {
            $table->unique(['institusi_id', 'kode_mk', 'kode'], 'cpmk_inst_mk_kode_unique');
        });
        Schema::table('sub_cpmk', function (Blueprint $table) {
            $table->unique(['institusi_id', 'cpmk_id', 'kode'], 'sub_cpmk_inst_cpmk_kode_unique');
        });
    }

    public function down(): void
    {
        // Data duplikat tidak dapat dipulihkan; cukup lepas UNIQUE index.
        Schema::table('sub_cpmk', function (Blueprint $table) {
            $table->dropUnique('sub_cpmk_inst_cpmk_kode_unique');
        });
        Schema::table('cpmk', function (Blueprint $table) {
            $table->dropUnique('cpmk_inst_mk_kode_unique');
        });
    }
};
