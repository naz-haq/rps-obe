<?php

namespace Tests\Feature;

use App\Models\Cpl;
use App\Models\GenerateSession;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkCpl;
use App\Models\User;
use App\Services\Ai\AiOutcome;
use App\Services\Ai\AiService;
use App\Services\Ai\LlmResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class CandidatePatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.default' => 'null',
            'logging.deprecations.channel' => 'null',
            'generator.rag.enabled' => false,
            'generator.grounding.enabled' => false,
        ]);
        Http::preventStrayRequests();
        $this->mock(AiService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('run');
        });
    }

    public function test_candidate_is_read_only_then_apply_changes_only_the_accepted_target_and_review_flags(): void
    {
        $session = $this->generateSession();
        $before = $session->fresh()->getAttributes();
        $draft = $session->draf;
        $revision = (int) $session->revisi;
        $proposal = [
            'kode' => 'AI-CANNOT-RENAME',
            'deskripsi' => 'Rumusan usulan AI untuk ditinjau dosen.',
            'cpl_kode' => ['CPL-AI-CANNOT-REMAP'],
            'taksonomi_kode' => ['C4'],
        ];
        $outcome = new AiOutcome(new LlmResult(
            text: json_encode(['cpmk' => [$proposal]], JSON_THROW_ON_ERROR),
            inputTokens: 120,
            outputTokens: 40,
            modelVersion: 'unit-test-model',
        ), 0.00125);

        // Replace the setUp shouldNotReceive mock, rather than adding a
        // conflicting expectation to it. Prompt construction still runs normally.
        $this->mock(AiService::class, function (MockInterface $mock) use ($session, $outcome): void {
            $mock->shouldReceive('run')->once()->withArgs(
                function (string $task, string $system, string $prompt, array $options) use ($session): bool {
                    $this->assertSame('generate', $task);
                    $this->assertNotSame('', $system);
                    $this->assertStringContainsString('FAR-CANDIDATE', $prompt);
                    $this->assertStringContainsString('Farmakologi Uji Kandidat', $prompt);
                    $this->assertStringContainsString('Rumusan lama.', $prompt);
                    $this->assertStringContainsString('CPL-1', $prompt);
                    $this->assertSame($session->institusi_id, $options['institusi_id']);
                    $this->assertSame($session->user_id, $options['user_id']);
                    $this->assertSame($session->id, $options['entity_id']);
                    $this->assertSame('revisi_item:cpmk', $options['mode']);
                    $this->assertTrue($options['no_cache']);

                    return true;
                },
            )->andReturn($outcome);
        });

        $response = $this->postJson("/api/v1/generate-sessions/{$session->id}/item-candidate", [
            'stage' => 'cpmk',
            'item_id' => 'cpmk-1',
            'action' => 'perbaiki_redaksi',
            'instruction' => 'Pertahankan pemetaan CPL dan perjelas rumusan.',
        ])->assertOk()
            ->assertJsonPath('data.stage', 'cpmk')
            ->assertJsonPath('data.item_id', 'cpmk-1')
            ->assertJsonPath('data.base_revisi', $revision)
            ->assertJsonPath('data.before.deskripsi', 'Rumusan lama.')
            ->assertJsonPath('data.after.kode', 'CPMK1')
            ->assertJsonPath('data.after.cpl_kode', ['CPL-1'])
            ->assertJsonPath('data.after.deskripsi', $proposal['deskripsi'])
            ->assertJsonPath('data.usage.estimated_usd', 0.00125);

        $this->assertSame($before, $session->fresh()->getAttributes());
        $this->assertSame($draft, $session->fresh()->draf);
        $this->assertSame($revision, (int) $session->fresh()->revisi);
        $candidate = $response->json('data');
        $accepted = $candidate['after'];
        // Apply what the human accepted, not the original AI proposal.
        $accepted['deskripsi'] = 'Rumusan final setelah ditinjau dosen.';
        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-apply", [
            'stage' => $candidate['stage'],
            'item_id' => $candidate['item_id'],
            'after' => $accepted,
            'base_revisi' => $candidate['base_revisi'],
        ])->assertOk();

        $expected = $draft;
        $expected['cpmk']['cpmk'][0]['deskripsi'] = $accepted['deskripsi'];
        $expected['cpmk']['cpmk'][0]['taksonomi_kode'] = ['C4'];
        $expected['sub_cpmk']['sub_cpmk'][0]['_needs_review'] = true;
        $expected['mingguan']['minggu'][0]['_needs_review'] = true;
        $expected['penilaian']['komponen'][0]['_needs_review'] = true;
        $session->refresh();
        $this->assertEquals($expected, $session->draf);
        $this->assertSame($draft['cpmk']['cpmk'][1], $session->draf['cpmk']['cpmk'][1]);
        $this->assertSame($draft['sub_cpmk']['sub_cpmk'][1], $session->draf['sub_cpmk']['sub_cpmk'][1]);
        $this->assertSame($revision + 1, (int) $session->revisi);
        $this->assertSame($before['status_bagian'], $session->getAttributes()['status_bagian']);
        foreach (['cpmk', 'sub_cpmk', 'indikator', 'rps_version', 'rps_approval_log'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Http::assertNothingSent();
    }

    public function test_partial_sub_cpmk_apply_preserves_omitted_indicators_taxonomy_and_parent(): void
    {
        $session = $this->generateSession();
        $draft = $session->draf;

        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-apply", [
            'stage' => 'sub_cpmk',
            'item_id' => 'sub-1',
            'after' => [
                'kode' => 'Sub-CPMK1.1',
                'deskripsi' => 'Turunan diperjelas tanpa mengganti indikator.',
            ],
            'base_revisi' => 0,
        ])->assertOk();

        $expected = $draft;
        $expected['sub_cpmk']['sub_cpmk'][0]['deskripsi'] = 'Turunan diperjelas tanpa mengganti indikator.';
        $expected['mingguan']['minggu'][0]['_needs_review'] = true;
        $expected['penilaian']['komponen'][0]['_needs_review'] = true;
        $session->refresh();
        $this->assertEquals($expected, $session->draf);
        $this->assertSame($draft['sub_cpmk']['sub_cpmk'][0]['indikator'], $session->draf['sub_cpmk']['sub_cpmk'][0]['indikator']);
        $this->assertSame(['C3'], $session->draf['sub_cpmk']['sub_cpmk'][0]['taksonomi_kode']);
        $this->assertSame('CPMK1', $session->draf['sub_cpmk']['sub_cpmk'][0]['cpmk_kode']);
        $this->assertSame('sub-1', $session->draf['sub_cpmk']['sub_cpmk'][0]['_id']);
        $this->assertSame(1, (int) $session->revisi);
        Http::assertNothingSent();
    }

    public function test_apply_cannot_change_cpl_mapping_even_when_the_submitted_mapping_is_valid(): void
    {
        $session = $this->generateSession();
        $original = $session->draf['cpmk']['cpmk'][0];

        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-apply", [
            'stage' => 'cpmk',
            'item_id' => 'cpmk-1',
            'after' => [
                'kode' => 'CPMK1',
                'deskripsi' => 'Redaksi boleh berubah, pemetaan tidak.',
                'cpl_kode' => ['CPL-2'],
            ],
            'base_revisi' => 0,
        ])->assertOk();

        $updated = $session->fresh();
        $this->assertSame($original['cpl_kode'], $updated->draf['cpmk']['cpmk'][0]['cpl_kode']);
        $this->assertSame($original['_id'], $updated->draf['cpmk']['cpmk'][0]['_id']);
        $this->assertSame('Redaksi boleh berubah, pemetaan tidak.', $updated->draf['cpmk']['cpmk'][0]['deskripsi']);
        $this->assertSame(1, (int) $updated->revisi);
    }

    public function test_apply_rejects_a_malformed_stage_item(): void
    {
        $session = $this->generateSession();

        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-apply", [
            'stage' => 'cpmk',
            'item_id' => 'cpmk-1',
            'after' => ['kode' => 'CPMK1'],
            'base_revisi' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('after.deskripsi');

        $this->assertSame(0, $session->fresh()->revisi);
    }

    public function test_apply_preserves_identity_increments_revision_and_marks_all_descendants(): void
    {
        $session = $this->generateSession();

        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-apply", [
            'stage' => 'cpmk',
            'item_id' => 'cpmk-1',
            'after' => [
                'kode' => 'KODE-DILARANG-BERUBAH',
                'deskripsi' => 'Rumusan CPMK yang diperbaiki.',
                'cpl_kode' => ['CPL-1'],
                'taksonomi_kode' => ['C4'],
            ],
            'base_revisi' => 0,
        ])->assertOk();

        $updated = $session->fresh();
        $this->assertSame(1, $updated->revisi);
        $this->assertSame('CPMK1', $updated->draf['cpmk']['cpmk'][0]['kode']);
        $this->assertTrue($updated->draf['sub_cpmk']['sub_cpmk'][0]['_needs_review']);
        $this->assertTrue($updated->draf['mingguan']['minggu'][0]['_needs_review']);
        $this->assertTrue($updated->draf['penilaian']['komponen'][0]['_needs_review']);
        $this->assertArrayNotHasKey('_needs_review', $updated->draf['sub_cpmk']['sub_cpmk'][1]);
    }

    public function test_apply_rejects_a_stale_revision_without_mutating_the_draft(): void
    {
        $session = $this->generateSession();
        $session->update(['revisi' => 2]);

        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-apply", [
            'stage' => 'cpmk',
            'item_id' => 'cpmk-1',
            'after' => [
                'kode' => 'CPMK1',
                'deskripsi' => 'Tidak boleh tersimpan.',
                'cpl_kode' => ['CPL-1'],
            ],
            'base_revisi' => 1,
        ])->assertConflict();

        $updated = $session->fresh();
        $this->assertSame(2, $updated->revisi);
        $this->assertSame('Rumusan lama.', $updated->draf['cpmk']['cpmk'][0]['deskripsi']);
    }

    public function test_apply_rejects_a_pinned_item(): void
    {
        $session = $this->generateSession();
        $draft = $session->draf;
        $draft['cpmk']['cpmk'][0]['_pin'] = true;
        $session->update(['draf' => $draft]);

        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-apply", [
            'stage' => 'cpmk',
            'item_id' => 'cpmk-1',
            'after' => [
                'kode' => 'CPMK1',
                'deskripsi' => 'Tidak boleh tersimpan.',
                'cpl_kode' => ['CPL-1'],
            ],
            'base_revisi' => 0,
        ])->assertUnprocessable();
    }

    public function test_item_suggest_fills_empty_fields_preserves_lecturer_choices_and_never_touches_the_draft(): void
    {
        $session = $this->generateSession();
        $before = $session->fresh()->getAttributes();

        $outcome = new AiOutcome(new LlmResult(
            text: json_encode(['sub_cpmk' => [[
                'kode' => 'AI-COBA-GANTI-KODE',
                'cpmk_kode' => 'CPMK2',
                'deskripsi' => 'Mahasiswa mampu menguraikan fase farmakokinetika obat.',
                'taksonomi_kode' => ['C4'],
                'indikator' => ['Ketepatan menguraikan fase ADME.'],
                'bidang_liar' => 'harus dibuang',
            ]]], JSON_THROW_ON_ERROR),
            inputTokens: 100,
            outputTokens: 50,
            modelVersion: 'unit-test-model',
        ), 0.002);

        $this->mock(AiService::class, function (MockInterface $mock) use ($session, $outcome): void {
            $mock->shouldReceive('run')->once()->withArgs(
                function (string $task, string $system, string $prompt, array $options) use ($session): bool {
                    $this->assertSame('generate', $task);
                    $this->assertStringContainsString('MODE LENGKAPI SATU ITEM', $prompt);
                    $this->assertStringContainsString('Sub-CPMK1.9', $prompt);
                    $this->assertSame($session->institusi_id, $options['institusi_id']);
                    $this->assertSame('isi_item:sub_cpmk', $options['mode']);
                    $this->assertTrue($options['no_cache']);

                    return true;
                },
            )->andReturn($outcome);
        });

        // Deskripsi kosong & indikator kosong dianggap belum terisi; kode &
        // CPMK induk adalah pilihan dosen dan tidak boleh diganti AI.
        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-suggest", [
            'stage' => 'sub_cpmk',
            'item' => ['kode' => 'Sub-CPMK1.9', 'cpmk_kode' => 'CPMK1', 'deskripsi' => '', 'indikator' => []],
        ])->assertOk()
            ->assertJsonPath('data.stage', 'sub_cpmk')
            ->assertJsonPath('data.item.kode', 'Sub-CPMK1.9')
            ->assertJsonPath('data.item.cpmk_kode', 'CPMK1')
            ->assertJsonPath('data.item.deskripsi', 'Mahasiswa mampu menguraikan fase farmakokinetika obat.')
            ->assertJsonPath('data.item.taksonomi_kode', ['C4'])
            ->assertJsonPath('data.item.indikator', ['Ketepatan menguraikan fase ADME.'])
            ->assertJsonMissingPath('data.item.bidang_liar')
            ->assertJsonPath('data.usage.estimated_usd', 0.002);

        $this->assertSame($before, $session->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_item_suggest_is_rejected_on_a_committed_session(): void
    {
        $session = $this->generateSession();
        $session->update(['status' => 'committed']);

        $this->postJson("/api/v1/generate-sessions/{$session->id}/item-suggest", [
            'stage' => 'cpmk',
            'item' => ['kode' => 'CPMK9'],
        ])->assertUnprocessable();
    }

    private function generateSession(): GenerateSession
    {
        $institution = Institusi::create([
            'kode' => 'TENANT-A',
            'nama' => 'Tenant A',
            'jenis' => 'prodi',
        ]);
        $actor = User::factory()->create(['institusi_id' => $institution->id]);
        Sanctum::actingAs($actor);
        // Like the lifecycle fixture, provide a real course/curriculum/CPL graph
        // so candidate tests exercise buildPrompt, not a missing-MK rejection.
        $curriculum = Kurikulum::create([
            'institusi_id' => $institution->id,
            'kode' => 'KUR-CANDIDATE',
            'nama' => 'Kurikulum Uji Kandidat',
            'tahun' => '2026',
        ]);
        $course = MataKuliah::create([
            'institusi_id' => $institution->id,
            'kurikulum_id' => $curriculum->id,
            'kode_mk' => 'FAR-CANDIDATE',
            'nama' => 'Farmakologi Uji Kandidat',
            'jenis_mk' => 'murni',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 3,
        ]);
        foreach (['CPL-1', 'CPL-2'] as $code) {
            $cpl = Cpl::create([
                'institusi_id' => $institution->id,
                'kurikulum_id' => $curriculum->id,
                'kode' => $code,
                'deskripsi' => "Menganalisis penggunaan obat secara rasional ({$code}).",
            ]);
            MkCpl::create([
                'institusi_id' => $institution->id,
                'kode_mk' => $course->kode_mk,
                'cpl_id' => $cpl->id,
                'bobot' => 50,
            ]);
        }

        return GenerateSession::create([
            'institusi_id' => $institution->id,
            'mk_id' => $course->id,
            'user_id' => $actor->id,
            'revisi' => 0,
            'draf' => [
                'cpmk' => ['cpmk' => [
                    ['_id' => 'cpmk-1', 'kode' => 'CPMK1', 'deskripsi' => 'Rumusan lama.', 'cpl_kode' => ['CPL-1']],
                    ['_id' => 'cpmk-2', 'kode' => 'CPMK2', 'deskripsi' => 'Rumusan lain tetap.', 'cpl_kode' => ['CPL-2']],
                ]],
                'sub_cpmk' => ['sub_cpmk' => [
                    [
                        '_id' => 'sub-1',
                        'kode' => 'Sub-CPMK1.1',
                        'cpmk_kode' => 'CPMK1',
                        'deskripsi' => 'Turunan terkait.',
                        'indikator' => ['Ketepatan analisis dosis.', 'Ketepatan penjelasan respons.'],
                        'taksonomi_kode' => ['C3'],
                    ],
                    ['_id' => 'sub-2', 'kode' => 'Sub-CPMK2.1', 'cpmk_kode' => 'CPMK2', 'deskripsi' => 'Turunan lain.'],
                ]],
                'mingguan' => ['minggu' => [
                    ['_id' => 'week-1', 'minggu_ke' => 1, 'sub_cpmk_kode' => 'Sub-CPMK1.1', 'materi_pustaka' => 'Materi'],
                ]],
                'penilaian' => ['komponen' => [
                    ['_id' => 'assessment-1', 'nama' => 'Tugas', 'sub_cpmk_kode' => 'Sub-CPMK1.1', 'bobot_persen' => 100],
                ]],
            ],
            'status_bagian' => [
                'cpmk' => 'draft',
                'sub_cpmk' => 'draft',
                'mingguan' => 'draft',
                'penilaian' => 'draft',
            ],
        ]);
    }
}
