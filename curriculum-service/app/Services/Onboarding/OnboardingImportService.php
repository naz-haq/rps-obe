<?php

namespace App\Services\Onboarding;

use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\ProfilLulusan;
use Illuminate\Support\Facades\DB;

/**
 * Modul 0 — Onboarding & Column-Mapping.
 * Parse CSV / rows JSON, sarankan pemetaan kolom (deterministik), lalu impor
 * baris ke entitas kurikulum (CPL / MATA_KULIAH / BAHAN_KAJIAN) per KURIKULUM.
 * Ekstraksi PDF/DOCX ditunda ke microservice Python; layer ini menerima data
 * yang sudah terstruktur (CSV teks atau rows dari frontend).
 */
class OnboardingImportService
{
    /** Jenis file yang didukung + skema field targetnya. */
    public const JENIS = ['cpl', 'mata_kuliah', 'bahan_kajian', 'profil_lulusan'];

    /**
     * Field target per jenis: nama => [wajib, tipe].
     * tipe: string|int (int di-cast + divalidasi numerik).
     *
     * @var array<string,array<string,array{wajib:bool,tipe:string}>>
     */
    private const FIELDS = [
        'cpl' => [
            'kode'       => ['wajib' => true,  'tipe' => 'string'],
            'deskripsi'  => ['wajib' => true,  'tipe' => 'string'],
            'aspek'      => ['wajib' => false, 'tipe' => 'string'],
            'level_kkni' => ['wajib' => false, 'tipe' => 'string'],
            'sumber'     => ['wajib' => false, 'tipe' => 'string'],
        ],
        'mata_kuliah' => [
            'kode_mk'           => ['wajib' => true,  'tipe' => 'string'],
            'nama'              => ['wajib' => true,  'tipe' => 'string'],
            'jenis_mk'          => ['wajib' => false, 'tipe' => 'string'],
            'sifat'             => ['wajib' => false, 'tipe' => 'string'],
            'rumpun'            => ['wajib' => false, 'tipe' => 'string'],
            'deskripsi_singkat' => ['wajib' => false, 'tipe' => 'string'],
            'sks_teori'         => ['wajib' => false, 'tipe' => 'int'],
            'sks_praktik'       => ['wajib' => false, 'tipe' => 'int'],
            'semester'          => ['wajib' => false, 'tipe' => 'int'],
            'prodi_kode'        => ['wajib' => false, 'tipe' => 'string'],
            'prasyarat_kode'    => ['wajib' => false, 'tipe' => 'string'],
        ],
        'bahan_kajian' => [
            'nama'      => ['wajib' => true,  'tipe' => 'string'],
            'deskripsi' => ['wajib' => false, 'tipe' => 'string'],
        ],
        'profil_lulusan' => [
            'kode'      => ['wajib' => true, 'tipe' => 'string'],
            'deskripsi' => ['wajib' => true, 'tipe' => 'string'],
        ],
    ];

    /**
     * Kata kunci header -> field target (deterministik, urut spesifik dulu).
     *
     * @var array<string,array<int,string>>
     */
    private const KEYWORD = [
        'sks_praktik'       => ['sks praktik', 'sks praktikum', 'praktikum', 'praktik'],
        'sks_teori'         => ['sks teori', 'teori', 'sks'],
        'kode_mk'           => ['kode mk', 'kode matakuliah', 'kode mata kuliah', 'kode_mk'],
        'kode'              => ['kode cpl', 'kode'],
        'deskripsi_singkat' => ['deskripsi singkat', 'deskripsi mk', 'ringkasan'],
        'deskripsi'         => ['deskripsi', 'capaian', 'uraian'],
        'nama'              => ['nama mk', 'nama mata kuliah', 'mata kuliah', 'nama', 'judul'],
        'aspek'             => ['aspek', 'ranah', 'domain'],
        'level_kkni'        => ['kkni', 'level', 'jenjang'],
        'sumber'            => ['sumber', 'referensi', 'asal'],
        'jenis_mk'          => ['jenis mk', 'jenis', 'tipe mk'],
        'sifat'             => ['sifat', 'wajib/pilihan'],
        'rumpun'            => ['rumpun', 'kelompok'],
        'semester'          => ['semester', 'smt'],
        'prodi_kode'        => ['prodi', 'program studi'],
        'prasyarat_kode'    => ['prasyarat', 'prerequisite'],
    ];

    /**
     * Uraikan CSV menjadi header + baris asosiatif.
     *
     * @return array{headers:array<int,string>,rows:array<int,array<string,string>>}
     */
    public function parseCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $lines = array_values(array_filter($lines, static fn($l) => trim($l) !== ''));

        if ($lines === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $rows = [];

        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Normalkan rows JSON: bila array-of-objects langsung dipakai; bila
     * array-of-arrays baris pertama dianggap header.
     *
     * @param  array<int,mixed>  $rows
     * @return array{headers:array<int,string>,rows:array<int,array<string,string>>}
     */
    public function normalizeRows(array $rows): array
    {
        if ($rows === []) {
            return ['headers' => [], 'rows' => []];
        }

        $first = $rows[0];

        // array-of-arrays: baris pertama = header
        if (array_is_list($first ?? [])) {
            $headers = array_map(static fn($h) => trim((string) $h), $first);
            $out = [];
            foreach (array_slice($rows, 1) as $r) {
                $row = [];
                foreach ($headers as $i => $h) {
                    $row[$h] = isset($r[$i]) ? trim((string) $r[$i]) : '';
                }
                $out[] = $row;
            }

            return ['headers' => $headers, 'rows' => $out];
        }

        // array-of-objects
        $headers = array_map('strval', array_keys($first));
        $out = [];
        foreach ($rows as $r) {
            $row = [];
            foreach ($headers as $h) {
                $row[$h] = isset($r[$h]) ? trim((string) $r[$h]) : '';
            }
            $out[] = $row;
        }

        return ['headers' => $headers, 'rows' => $out];
    }

    /**
     * Sarankan pemetaan header -> field target (deterministik via kata kunci).
     *
     * @param  array<int,string>  $headers
     * @return array<string,string> header => field (hanya yang cocok)
     */
    public function suggestMapping(string $jenis, array $headers): array
    {
        $fields = self::FIELDS[$jenis] ?? [];
        $mapping = [];
        $terpakai = [];

        foreach ($headers as $header) {
            $norm = $this->normalize($header);
            foreach (self::KEYWORD as $field => $kunci) {
                if (! isset($fields[$field]) || in_array($field, $terpakai, true)) {
                    continue;
                }
                foreach ($kunci as $k) {
                    if (str_contains($norm, $k)) {
                        $mapping[$header] = $field;
                        $terpakai[] = $field;
                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Impor rows ke entitas kurikulum sesuai pemetaan (upsert by kunci natural).
     *
     * @param  array<int,array<string,string>>  $rows
     * @param  array<string,string>  $mapping  header => field target
     * @return array{dibuat:int,diperbarui:int,dilewati:int,galat:array<int,string>}
     */
    public function import(int $institusiId, int $kurikulumId, string $jenis, array $rows, array $mapping): array
    {
        $fields = self::FIELDS[$jenis];
        $dibuat = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $galat = [];

        DB::transaction(function () use (
            $institusiId,
            $kurikulumId,
            $jenis,
            $rows,
            $mapping,
            $fields,
            &$dibuat,
            &$diperbarui,
            &$dilewati,
            &$galat
        ) {
            foreach ($rows as $i => $row) {
                $atribut = $this->petakanBaris($row, $mapping, $fields, $i + 1, $galat);
                if ($atribut === null) {
                    $dilewati++;
                    continue;
                }

                $hasil = $this->simpan($institusiId, $kurikulumId, $jenis, $atribut);
                $hasil === 'dibuat' ? $dibuat++ : $diperbarui++;
            }
        });

        return compact('dibuat', 'diperbarui', 'dilewati', 'galat');
    }

    /**
     * Petakan satu baris mentah -> atribut field target (cast + validasi wajib).
     *
     * @param  array<string,string>  $row
     * @param  array<string,string>  $mapping
     * @param  array<string,array{wajib:bool,tipe:string}>  $fields
     * @param  array<int,string>  $galat
     * @return array<string,mixed>|null null bila baris tak valid (dilewati)
     */
    private function petakanBaris(array $row, array $mapping, array $fields, int $nomor, array &$galat): ?array
    {
        $atribut = [];

        foreach ($mapping as $header => $field) {
            if (! isset($fields[$field])) {
                continue;
            }
            $nilai = trim((string) ($row[$header] ?? ''));
            if ($nilai === '') {
                continue;
            }
            if ($fields[$field]['tipe'] === 'int') {
                if (! is_numeric($nilai)) {
                    $galat[] = "Baris {$nomor}: '{$field}' bukan angka ('{$nilai}'), diabaikan.";
                    continue;
                }
                $nilai = (int) $nilai;
            }
            $atribut[$field] = $nilai;
        }

        foreach ($fields as $field => $def) {
            if ($def['wajib'] && (! isset($atribut[$field]) || $atribut[$field] === '')) {
                $galat[] = "Baris {$nomor}: field wajib '{$field}' kosong, baris dilewati.";

                return null;
            }
        }

        return $atribut;
    }

    /**
     * Simpan/upsert satu record sesuai jenis. Mengembalikan 'dibuat'|'diperbarui'.
     *
     * @param  array<string,mixed>  $atribut
     */
    private function simpan(int $institusiId, int $kurikulumId, string $jenis, array $atribut): string
    {
        [$model, $kunci] = match ($jenis) {
            'cpl'            => [Cpl::class, ['kode' => $atribut['kode']]],
            'mata_kuliah'    => [MataKuliah::class, ['kode_mk' => $atribut['kode_mk']]],
            'bahan_kajian'   => [BahanKajian::class, ['nama' => $atribut['nama']]],
            'profil_lulusan' => [ProfilLulusan::class, ['kode' => $atribut['kode']]],
        };

        $cari = array_merge(['institusi_id' => $institusiId, 'kurikulum_id' => $kurikulumId], $kunci);
        $record = $model::where($cari)->first();

        if ($record) {
            $record->fill($atribut)->save();

            return 'diperbarui';
        }

        $model::create(array_merge($cari, $atribut));

        return 'dibuat';
    }

    /** Guard: kurikulum harus milik institusi. */
    public function kurikulumMilik(int $institusiId, int $kurikulumId): bool
    {
        return Kurikulum::whereKey($kurikulumId)->where('institusi_id', $institusiId)->exists();
    }

    private function normalize(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($s)) ?? '');
    }
}
