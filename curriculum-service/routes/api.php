<?php

use App\Http\Controllers\Api\AiAsistifController;
use App\Http\Controllers\Api\AiPengaturanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadanRujukanController;
use App\Http\Controllers\Api\BahanKajianController;
use App\Http\Controllers\Api\ButirAcuanController;
use App\Http\Controllers\Api\CapaianMahasiswaController;
use App\Http\Controllers\Api\CplController;
use App\Http\Controllers\Api\DokumenRujukanController;
use App\Http\Controllers\Api\EvaluasiCplController;
use App\Http\Controllers\Api\GenerateSessionController;
use App\Http\Controllers\Api\GovernanceController;
use App\Http\Controllers\Api\InstitusiController;
use App\Http\Controllers\Api\KerangkaAcuanController;
use App\Http\Controllers\Api\KonfigurasiAturanController;
use App\Http\Controllers\Api\KurikulumController;
use App\Http\Controllers\Api\MataKuliahController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PemenuhanAcuanController;
use App\Http\Controllers\Api\PetaKurikulumController;
use App\Http\Controllers\Api\ProfilLulusanController;
use App\Http\Controllers\Api\PromptTemplateController;
use App\Http\Controllers\Api\RpsAiController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RpsApprovalController;
use App\Http\Controllers\Api\RpsVersionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TaksonomiController;
use App\Http\Controllers\Api\TemplateRpsController;
use App\Http\Controllers\Api\TargetCplController;
use App\Http\Controllers\Api\TindakLanjutController;
use App\Http\Controllers\Api\ValidasiOverlapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Curriculum Service (prefix: /api/v1)
|--------------------------------------------------------------------------
| Auth (Keycloak/JWT) DITUNDA ke fase akhir. Selama pengembangan endpoint
| dibiarkan terbuka agar mudah diuji, mengikuti pola Survey Service.
*/

Route::get('/health', fn() => response()->json([
    'service' => 'curriculum-service',
    'status' => 'ok',
    'version' => '0.1.0',
]));

// Modul Auth & RBAC (Sanctum + spatie/permission)
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('auth/password', [AuthController::class, 'updatePassword']);

    // Katalog izin untuk matriks ceklist (butuh minimal lihat peran).
    Route::get('rbac/katalog', [RoleController::class, 'katalog'])->middleware('permission:role.view');

    Route::middleware('permission:role.view')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/{role}', [RoleController::class, 'show']);
    });
    Route::middleware('permission:role.manage')->group(function () {
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    });

    Route::middleware('permission:user.view')->group(function () {
        Route::get('users', [UserController::class, 'index']);
    });
    Route::middleware('permission:user.manage')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });

    // Prodi/Unit (institusi). Daftar bersifat lookup (dipakai dropdown di form
    // kurikulum/mata kuliah/pengguna), jadi cukup terautentikasi. Perubahan data
    // tetap dijaga izin prodi.manage.
    Route::get('institusi', [InstitusiController::class, 'index']);
    Route::middleware('permission:prodi.manage')->group(function () {
        Route::post('institusi', [InstitusiController::class, 'store']);
        Route::put('institusi/{institusi}', [InstitusiController::class, 'update']);
        Route::delete('institusi/{institusi}', [InstitusiController::class, 'destroy']);
    });
});

// Pengaturan AI (profil produksi/simulasi) — dapat diubah dari UI tanpa deploy
Route::get('ai/pengaturan', [AiPengaturanController::class, 'show']);
Route::put('ai/pengaturan', [AiPengaturanController::class, 'update']);

// AI Asistif inline (perbaiki/parafrase/ringkas satu field)
Route::post('ai/asistif', [AiAsistifController::class, 'asistif']);

// Modul 1 — Konfigurasi Aturan (diisi manual, otoritatif)
Route::get('konfigurasi-aturan', [KonfigurasiAturanController::class, 'index']);
Route::post('konfigurasi-aturan/upsert', [KonfigurasiAturanController::class, 'upsert']);
Route::delete('konfigurasi-aturan/{konfigurasiAturan}', [KonfigurasiAturanController::class, 'destroy']);

// Template/format dokumen RPS untuk cetak seragam (unggah berkas + tandai aktif)
Route::get('template-rps', [TemplateRpsController::class, 'index']);
Route::post('template-rps', [TemplateRpsController::class, 'store']);
Route::post('template-rps/{template}/activate', [TemplateRpsController::class, 'activate']);
Route::get('template-rps/{template}/download', [TemplateRpsController::class, 'download']);
Route::put('template-rps/{template}', [TemplateRpsController::class, 'update']);
Route::delete('template-rps/{template}', [TemplateRpsController::class, 'destroy']);

// Prompt AI terpusat: katalog slot bawaan + CRUD override (per-tenant/jenis_mk)
Route::get('prompts/catalog', [PromptTemplateController::class, 'catalog']);
Route::apiResource('prompt-templates', PromptTemplateController::class);

// Modul 0 — Onboarding & Column-Mapping
Route::post('onboarding/preview', [OnboardingController::class, 'preview']);
Route::get('onboarding/mapping', [OnboardingController::class, 'mappingIndex']);
Route::post('onboarding/mapping', [OnboardingController::class, 'mappingStore']);
Route::post('onboarding/import', [OnboardingController::class, 'import']);

// Modul 1 — Peta Kurikulum
Route::apiResource('kurikulum', KurikulumController::class);
Route::apiResource('profil-lulusan', ProfilLulusanController::class);
Route::apiResource('cpl', CplController::class);
Route::apiResource('mata-kuliah', MataKuliahController::class);
Route::apiResource('bahan-kajian', BahanKajianController::class);

// Modul 1 — Taksonomi master (Bloom/Krathwohl/Dave + kata kerja operasional)
Route::apiResource('taksonomi', TaksonomiController::class)->parameters(['taksonomi' => 'taksonomi']);

Route::get('kurikulum/{kurikulum}/matriks', [PetaKurikulumController::class, 'matriks']);
Route::post('kurikulum/{kurikulum}/matriks/link', [PetaKurikulumController::class, 'link']);
Route::delete('kurikulum/{kurikulum}/matriks/link', [PetaKurikulumController::class, 'unlink']);
Route::post('kurikulum/{kurikulum}/matriks/suggest', [PetaKurikulumController::class, 'suggestMataKuliah']);
Route::get('kurikulum/{kurikulum}/matriks-bahan-kajian', [PetaKurikulumController::class, 'matriksBahanKajian']);
Route::post('kurikulum/{kurikulum}/matriks-bahan-kajian/link', [PetaKurikulumController::class, 'linkBahanKajian']);
Route::delete('kurikulum/{kurikulum}/matriks-bahan-kajian/link', [PetaKurikulumController::class, 'unlinkBahanKajian']);
Route::post('kurikulum/{kurikulum}/matriks-bahan-kajian/suggest', [PetaKurikulumController::class, 'suggestBahanKajian']);
Route::get('kurikulum/{kurikulum}/matriks-mk-bahan-kajian', [PetaKurikulumController::class, 'matriksMkBahanKajian']);
Route::post('kurikulum/{kurikulum}/matriks-mk-bahan-kajian/link', [PetaKurikulumController::class, 'linkMkBahanKajian']);
Route::delete('kurikulum/{kurikulum}/matriks-mk-bahan-kajian/link', [PetaKurikulumController::class, 'unlinkMkBahanKajian']);
Route::post('kurikulum/{kurikulum}/matriks-mk-bahan-kajian/suggest', [PetaKurikulumController::class, 'suggestMkBahanKajian']);
Route::get('kurikulum/{kurikulum}/matriks-profil-lulusan', [PetaKurikulumController::class, 'matriksProfilLulusan']);
Route::post('kurikulum/{kurikulum}/matriks-profil-lulusan/link', [PetaKurikulumController::class, 'linkProfilLulusan']);
Route::delete('kurikulum/{kurikulum}/matriks-profil-lulusan/link', [PetaKurikulumController::class, 'unlinkProfilLulusan']);
Route::post('kurikulum/{kurikulum}/matriks-profil-lulusan/suggest', [PetaKurikulumController::class, 'suggestProfilLulusan']);
Route::get('kurikulum/{kurikulum}/traceability', [PetaKurikulumController::class, 'traceability']);

// Modul 2 — RPS Generator (bertahap + grounding)
Route::apiResource('generate-sessions', GenerateSessionController::class)
    ->only(['index', 'store', 'show', 'destroy']);
Route::post('generate-sessions/{generateSession}/generate', [GenerateSessionController::class, 'generate']);
Route::post('generate-sessions/{generateSession}/accept', [GenerateSessionController::class, 'accept']);
Route::post('generate-sessions/{generateSession}/reject', [GenerateSessionController::class, 'reject']);
Route::post('generate-sessions/{generateSession}/pin', [GenerateSessionController::class, 'pin']);
Route::post('generate-sessions/{generateSession}/commit', [GenerateSessionController::class, 'commit']);

Route::get('rps-versions', [RpsVersionController::class, 'index']);
Route::get('rps-versions/{rpsVersion}', [RpsVersionController::class, 'show']);
Route::delete('rps-versions/{rpsVersion}', [RpsVersionController::class, 'destroy']);
Route::get('rps-versions/{rpsVersion}/traceability', [RpsVersionController::class, 'traceability']);
Route::get('rps-versions/{rpsVersion}/cetak', [RpsVersionController::class, 'cetak']);
Route::get('rps-versions/{rpsVersion}/docx', [RpsVersionController::class, 'unduhDocx']);

// Modul 2 — Layanan AI di atas RPS: audit keselarasan (#6) & chat konsultan (#7)
Route::post('generate-sessions/{generateSession}/audit', [RpsAiController::class, 'auditSession']);
Route::post('rps-versions/{rpsVersion}/audit', [RpsAiController::class, 'auditRpsVersion']);
Route::post('rps/ai/chat', [RpsAiController::class, 'chat']);

// Modul 3 — Validator Overlap (deteksi keterampilan diklaim >1 MK)
Route::get('validasi-overlap', [ValidasiOverlapController::class, 'index']);
Route::get('validasi-overlap/{validasiOverlap}', [ValidasiOverlapController::class, 'show']);
Route::post('validasi-overlap/pindai', [ValidasiOverlapController::class, 'pindai']);
Route::post('validasi-overlap/{validasiOverlap}/analisis', [ValidasiOverlapController::class, 'analisis']);
Route::put('validasi-overlap/{validasiOverlap}/review', [ValidasiOverlapController::class, 'review']);

// Modul 4 — Workflow Approval (Dosen → Kaprodi/STPMP, audit trail)
Route::get('persetujuan', [RpsApprovalController::class, 'antrian']);
Route::get('rps-versions/{rpsVersion}/riwayat-persetujuan', [RpsApprovalController::class, 'riwayat']);
Route::post('rps-versions/{rpsVersion}/ajukan', [RpsApprovalController::class, 'ajukan']);
Route::post('rps-versions/{rpsVersion}/setujui', [RpsApprovalController::class, 'setujui']);
Route::post('rps-versions/{rpsVersion}/revisi', [RpsApprovalController::class, 'revisi']);
Route::post('rps-versions/{rpsVersion}/tarik', [RpsApprovalController::class, 'tarik']);

// Modul 6 — OBAEI (evaluasi ketercapaian CPL + tindak lanjut, closing the loop)
Route::get('obaei/agregasi', [EvaluasiCplController::class, 'agregasi']);

Route::get('target-cpl', [TargetCplController::class, 'index']);
Route::post('target-cpl', [TargetCplController::class, 'store']);
Route::put('target-cpl/{targetCpl}', [TargetCplController::class, 'update']);
Route::delete('target-cpl/{targetCpl}', [TargetCplController::class, 'destroy']);

Route::get('capaian-mahasiswa', [CapaianMahasiswaController::class, 'index']);
Route::post('capaian-mahasiswa', [CapaianMahasiswaController::class, 'store']);
Route::put('capaian-mahasiswa/{capaianMahasiswa}', [CapaianMahasiswaController::class, 'update']);
Route::delete('capaian-mahasiswa/{capaianMahasiswa}', [CapaianMahasiswaController::class, 'destroy']);

Route::get('evaluasi-cpl', [EvaluasiCplController::class, 'index']);
Route::get('evaluasi-cpl/{evaluasiCpl}', [EvaluasiCplController::class, 'show']);
Route::post('evaluasi-cpl', [EvaluasiCplController::class, 'store']);
Route::put('evaluasi-cpl/{evaluasiCpl}', [EvaluasiCplController::class, 'update']);
Route::post('evaluasi-cpl/{evaluasiCpl}/analisis', [EvaluasiCplController::class, 'analisis']);
Route::post('evaluasi-cpl/{evaluasiCpl}/finalisasi', [EvaluasiCplController::class, 'finalisasi']);
Route::delete('evaluasi-cpl/{evaluasiCpl}', [EvaluasiCplController::class, 'destroy']);

Route::post('evaluasi-cpl/{evaluasiCpl}/tindak-lanjut', [TindakLanjutController::class, 'store']);
Route::put('tindak-lanjut/{tindakLanjut}', [TindakLanjutController::class, 'update']);
Route::delete('tindak-lanjut/{tindakLanjut}', [TindakLanjutController::class, 'destroy']);

// Modul 0a — Dokumen Rujukan (Doc-intel + RAG)
Route::apiResource('badan-rujukan', BadanRujukanController::class);
Route::get('dokumen-rujukan', [DokumenRujukanController::class, 'index']);
Route::post('dokumen-rujukan', [DokumenRujukanController::class, 'store']);
Route::get('dokumen-rujukan/search', [DokumenRujukanController::class, 'search']);
Route::get('dokumen-rujukan/{dokumenRujukan}', [DokumenRujukanController::class, 'show']);
Route::post('dokumen-rujukan/{dokumenRujukan}/reindex', [DokumenRujukanController::class, 'reindex']);
Route::delete('dokumen-rujukan/{dokumenRujukan}', [DokumenRujukanController::class, 'destroy']);

// Modul 0b — Checklist Penyelarasan Acuan
Route::apiResource('kerangka-acuan', KerangkaAcuanController::class);
Route::post('kerangka-acuan/{kerangkaAcuan}/butir', [ButirAcuanController::class, 'store']);
Route::put('butir-acuan/{butirAcuan}', [ButirAcuanController::class, 'update']);
Route::delete('butir-acuan/{butirAcuan}', [ButirAcuanController::class, 'destroy']);
Route::post('pemenuhan-acuan/upsert', [PemenuhanAcuanController::class, 'upsert']);

// Modul 8 — Tata Kelola & Monitoring (dashboard biaya/penggunaan + audit log + notifikasi)
Route::get('governance/ringkasan', [GovernanceController::class, 'ringkasan']);
Route::get('governance/penggunaan', [GovernanceController::class, 'penggunaan']);
Route::get('governance/audit-log', [GovernanceController::class, 'auditLog']);
Route::get('governance/notifikasi', [GovernanceController::class, 'notifikasi']);
Route::post('governance/notifikasi/{notifikasi}/dibaca', [GovernanceController::class, 'tandaiDibaca']);
