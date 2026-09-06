<?php

namespace Tests\Feature;

use App\Models\Cpl;
use App\Models\GenerateSession;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkCpl;
use App\Models\RpsApprovalLog;
use App\Models\RpsVersion;
use App\Models\User;
use App\Services\Ai\AiOutcome;
use App\Services\Ai\AiService;
use App\Services\Ai\LlmResult;
use App\Services\Approval\Exceptions\ApprovalException;
use App\Services\Approval\RpsApprovalService;
use App\Services\Generator\Exceptions\GeneratorException;
use App\Services\Generator\RpsGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contract tests for reopening staging, not unlocking an approved RPS.
 * No schema workarounds, paid AI, disabled tenant middleware, or fake audit logs.
 */
class GeneratorLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Institusi $institution;
    private User $actor;
    private MataKuliah $course;
    private RpsGeneratorService $generator;

    protected function setUp(): void
    {
        parent::setUp();

        // Local laravel.log may be owned by the web/container user. Only discard
        // diagnostic logging: approval/audit records must still reach the DB.
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

        $this->institution = Institusi::create([
            'kode' => 'LIFECYCLE-A',
            'nama' => 'Institusi Uji Lifecycle',
            'jenis' => 'prodi',
        ]);
        $this->actor = User::factory()->create([
            'institusi_id' => $this->institution->id,
            'name' => 'Penyusun Terautentikasi',
        ]);
        Sanctum::actingAs($this->actor);

        $curriculum = Kurikulum::create([
            'institusi_id' => $this->institution->id,
            'kode' => 'KUR-2026',
            'nama' => 'Kurikulum Uji',
            'tahun' => '2026',
        ]);
        $this->course = MataKuliah::create([
            'institusi_id' => $this->institution->id,
            'kurikulum_id' => $curriculum->id,
            'kode_mk' => 'FAR-LIFECYCLE',
            'nama' => 'Farmakologi Uji',
            'jenis_mk' => 'murni',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 3,
        ]);
        $cpl = Cpl::create([
            'institusi_id' => $this->institution->id,
            'kurikulum_id' => $curriculum->id,
            'kode' => 'CPL-1',
            'deskripsi' => 'Menganalisis penggunaan obat secara rasional.',
        ]);
        MkCpl::create([
            'institusi_id' => $this->institution->id,
            'kode_mk' => $this->course->kode_mk,
            'cpl_id' => $cpl->id,
            'bobot' => 100,
        ]);

        $this->generator = app(RpsGeneratorService::class);
    }

    public function test_manual_pipeline_commits_a_complete_rps_without_ai(): void
    {
        $session = $this->manualSession();
        $this->assertTrue($this->generator->readyToCommit($session));
        $this->assertSame('selesai', $session->status);

        $response = $this->postJson($this->url($session, 'commit'))->assertCreated();
        $session->refresh();
        $rps = $session->rpsVersion()->firstOrFail();

        $response->assertJsonPath('data.rps.id', $rps->id)
            ->assertJsonPath('data.session.status', 'committed');
        $this->assertSame('draft', $rps->status);
        $this->assertSame($this->actor->id, (int) $rps->created_by);
        $this->assertCommittedGraph($rps, 1);
        $this->assertDatabaseCount('rps_approval_log', 0);
    }

    #[DataProvider('reopenableStatuses')]
    public function test_reopen_preserves_draft_children_identity_and_history(string $status): void
    {
        $session = $this->committedSession($status);
        $rps = $session->rpsVersion()->firstOrFail();
        $before = $this->snapshot();
        $sessionBefore = $session->getAttributes();
        $rpsBefore = $rps->getAttributes();

        // The caller is deliberately NOT session.user_id, and supplied identity
        // fields must not replace either authenticated actor field in the log.
        $caller = User::factory()->create([
            'institusi_id' => $this->institution->id,
            'name' => 'Penyunting Kedua',
        ]);
        Sanctum::actingAs($caller);
        $this->postJson($this->url($session, 'reopen'), [
            'catatan' => 'Perbaikan rencana pembelajaran.',
            'actor_id' => $this->actor->id,
            'actor_nama' => 'Identitas palsu',
            'user_id' => $this->actor->id,
        ])->assertOk();

        $session->refresh();
        $rps->refresh();
        $this->assertSame('berjalan', $session->status);
        $this->assertSame($rps->id, (int) $session->rps_version_id);
        $this->assertSame('draft', $rps->status);
        $this->assertNull($rps->submitted_at);
        $this->assertNull($rps->approved_at);
        $this->assertGreaterThan((int) $sessionBefore['revisi'], (int) $session->revisi);

        foreach (['draf', 'status_bagian', 'konteks_tambahan', 'catatan_validasi', 'tahap', 'user_id'] as $field) {
            $this->assertSame($sessionBefore[$field], $session->getAttributes()[$field], $field);
        }
        foreach (['id', 'ulid', 'versi', 'kode_mk', 'institusi_id', 'created_by', 'created_at'] as $field) {
            $this->assertSame($rpsBefore[$field], $rps->getAttributes()[$field], $field);
        }
        $after = $this->snapshot();
        foreach ($this->childTables() as $table) {
            $this->assertSame($before[$table], $after[$table], $table);
        }
        $this->assertSame($before['rps_approval_log'], array_slice($after['rps_approval_log'], 0, -1));
        $log = $rps->approvalLogs()->orderByDesc('id')->firstOrFail();
        $this->assertSame('buka_draf', $log->aksi);
        $this->assertSame($status, $log->dari_status);
        $this->assertSame('draft', $log->ke_status);
        $this->assertSame('Perbaikan rencana pembelajaran.', $log->catatan);
        $this->assertSame($caller->id, (int) $log->actor_id);
        $this->assertSame($caller->name, $log->actor_nama);
        $this->assertSame($this->institution->id, (int) $log->institusi_id);
        $this->assertDatabaseCount('rps_version', 1);
    }

    public static function reopenableStatuses(): array
    {
        return ['draft' => ['draft'], 'review' => ['review'], 'revisi' => ['revisi']];
    }

    #[DataProvider('invalidNotes')]
    public function test_reopen_requires_a_non_blank_string_note(array $payload): void
    {
        $session = $this->committedSession();
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'reopen'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('catatan');

        $this->assertSame($before, $this->snapshot());
    }

    public static function invalidNotes(): array
    {
        return [
            'missing' => [[]],
            'null' => [['catatan' => null]],
            'empty' => [['catatan' => '']],
            'whitespace' => [['catatan' => '   ']],
            'number' => [['catatan' => 123]],
            'array' => [['catatan' => ['alasan']]],
        ];
    }

    #[DataProvider('nonCommittedSessions')]
    public function test_reopen_requires_a_committed_session_and_a_linked_rps(string $status, bool $linked): void
    {
        $session = $this->committedSession();
        $session->update([
            'status' => $status,
            'rps_version_id' => $linked ? $session->rps_version_id : null,
        ]);
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Tidak boleh dibuka.'])
            ->assertUnprocessable();

        $this->assertSame($before, $this->snapshot());
    }

    public static function nonCommittedSessions(): array
    {
        return [
            'berjalan linked' => ['berjalan', true],
            'selesai linked' => ['selesai', true],
            'batal linked' => ['batal', true],
            'berjalan unlinked' => ['berjalan', false],
            'selesai unlinked' => ['selesai', false],
            'batal unlinked' => ['batal', false],
            'committed unlinked' => ['committed', false],
        ];
    }

    #[DataProvider('approvalLocks')]
    public function test_reopening_an_approved_rps_starts_a_new_version_and_leaves_it_intact(string $lock): void
    {
        $session = $this->committedSession();
        $rps = $session->rpsVersion()->firstOrFail();
        // Isolate each independent lock, including legacy/inconsistent records.
        if ($lock === 'status') {
            $rps->update(['status' => 'approved']);
        } elseif ($lock === 'timestamp') {
            $rps->update(['approved_at' => now()]);
        } else {
            RpsApprovalLog::create([
                'institusi_id' => $this->institution->id,
                'rps_version_id' => $rps->id,
                'aksi' => 'setujui',
                'dari_status' => 'review',
                'ke_status' => 'approved',
                'catatan' => 'Riwayat persetujuan lama.',
                'actor_id' => $this->actor->id,
                'actor_nama' => $this->actor->name,
            ]);
        }
        $rps->refresh();
        $approvedAttrs = $rps->getAttributes();
        $weekIds = $rps->minggu()->pluck('id')->all();

        // Membuka RPS yang sudah disetujui KINI diperbolehkan: bukan menyunting di
        // tempat, melainkan memulai VERSI BARU. Versi yang disetujui tetap utuh.
        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Revisi menjadi versi baru.'])
            ->assertOk();

        // Versi disetujui tidak berubah sama sekali (status, timestamp, anak).
        $this->assertSame($approvedAttrs, $rps->fresh()->getAttributes());
        foreach ($weekIds as $id) {
            $this->assertDatabaseHas('rps_minggu', ['id' => $id]);
        }
        // Sesi lepas-kait dari versi disetujui; commit berikutnya = versi baru.
        $fresh = $session->fresh();
        $this->assertSame('berjalan', $fresh->status);
        $this->assertNull($fresh->rps_version_id);
        // Log buka_draf tercatat tanpa mengubah status versi disetujui.
        $this->assertSame('buka_draf', $rps->approvalLogs()->orderByDesc('id')->firstOrFail()->aksi);
    }

    public static function approvalLocks(): array
    {
        return ['approved status' => ['status'], 'approved_at' => ['timestamp'], 'setujui history' => ['history']];
    }

    public function test_approved_rps_is_final_cannot_be_deleted_or_given_new_meeting_details(): void
    {
        $session = $this->committedSession('approved');
        $rps = $session->rpsVersion()->firstOrFail();

        $this->deleteJson("/api/v1/rps-versions/{$rps->id}")->assertUnprocessable();
        $this->assertDatabaseHas('rps_version', ['id' => $rps->id]);

        $this->postJson("/api/v1/rps-versions/{$rps->id}/generate-pertemuan")->assertUnprocessable();
        $this->assertGeneratorRejected(fn() => $this->generator->generatePertemuan($rps->fresh()));
    }

    public function test_non_approved_committed_rps_can_still_be_deleted(): void
    {
        $session = $this->committedSession('review');
        $rps = $session->rpsVersion()->firstOrFail();

        $this->deleteJson("/api/v1/rps-versions/{$rps->id}")->assertOk();
        $this->assertDatabaseMissing('rps_version', ['id' => $rps->id]);
        $this->assertNull($session->fresh()->rps_version_id);
    }

    public function test_reopening_twice_is_rejected_without_a_second_log(): void
    {
        $session = $this->committedSession();
        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Buka satu kali.'])->assertOk();
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Buka lagi.'])->assertUnprocessable();

        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('rps_approval_log', 1);
    }

    public function test_foreign_tenant_cannot_reopen_a_committed_session(): void
    {
        $session = $this->committedSession('review');
        $foreign = Institusi::create(['kode' => 'LIFECYCLE-B', 'nama' => 'Institusi B', 'jenis' => 'prodi']);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $foreign->id]));
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Permintaan lintas institusi.'])
            ->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_unpin_changes_only_the_stage_pin_and_retains_item_pins(): void
    {
        $session = $this->manualSession();
        $this->generator->setItemPin($session, 'cpmk', 'cpmk-1', true);
        $this->generator->pinStage($session->fresh(), 'cpmk');
        $session->refresh();
        $draft = $session->draf;
        $statuses = $session->status_bagian;
        $statuses['cpmk'] = 'accepted';
        $revision = (int) $session->revisi;

        $this->postJson($this->url($session, 'unpin'), ['stage' => 'cpmk'])->assertOk();

        $session->refresh();
        $this->assertSame($statuses, $session->status_bagian);
        $this->assertSame($draft, $session->draf);
        $this->assertTrue($session->draf['cpmk']['cpmk'][0]['_pin']);
        $this->assertGreaterThan($revision, (int) $session->revisi);
        $this->assertTrue($this->generator->readyToCommit($session));
        $this->assertDatabaseCount('rps_approval_log', 0);
    }

    public function test_a_reopened_pinned_stage_must_be_unpinned_before_editing(): void
    {
        $session = $this->manualSession();
        $this->generator->pinStage($session, 'cpmk');
        $rps = $this->generator->commit($session->fresh());
        $this->generator->reopen($session->fresh(), $this->actorData());
        $draft = $session->fresh()->draf;
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'accept'), [
            'stage' => 'cpmk',
            'edited' => $draft['cpmk'],
        ])->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());

        $unlocked = $this->generator->unpinStage($session->fresh(), 'cpmk');
        $this->assertSame('accepted', $unlocked->status_bagian['cpmk']);
        $this->assertSame($draft, $unlocked->draf);
        $this->postJson($this->url($session, 'accept'), [
            'stage' => 'cpmk',
            'edited' => $draft['cpmk'],
        ])->assertOk();
        $this->assertSame($rps->id, (int) $session->fresh()->rps_version_id);
    }

    #[DataProvider('pinnableItems')]
    public function test_pinned_item_blocks_whole_stage_generate_and_reject_before_ai(string $stage, string $key, string $itemId): void
    {
        $session = $this->manualSession();
        $this->generator->setItemPin($session, $stage, $itemId, true);
        $session->refresh();
        $this->assertNotSame('pinned', $session->status_bagian[$stage]);
        $this->assertTrue($session->draf[$stage][$key][0]['_pin']);
        $before = $this->snapshot();

        // setUp forbids AiService::run; a late guard must not incur AI cost.
        foreach (['generate', 'reject'] as $action) {
            $this->postJson($this->url($session, $action), ['stage' => $stage])->assertUnprocessable();
            $this->assertSame($before, $this->snapshot(), $action);
        }
        $this->assertGeneratorRejected(fn() => $this->generator->generateStage($session->fresh(), $stage));
        $this->assertGeneratorRejected(fn() => $this->generator->rejectStage($session->fresh(), $stage));
        $this->assertSame($before, $this->snapshot());
        Http::assertNothingSent();
    }

    #[DataProvider('pinnableItems')]
    public function test_manual_stage_save_rejects_modifying_deleting_or_stripping_a_pinned_item(string $stage, string $key, string $itemId): void
    {
        $session = $this->manualSession();
        $this->generator->setItemPin($session, $stage, $itemId, true);
        $edited = $session->fresh()->draf[$stage];
        $modified = $edited;
        $field = match ($stage) {
            'mingguan' => 'materi_pustaka',
            'penilaian' => 'nama',
            default => 'deskripsi',
        };
        $modified[$key][0][$field] = 'Perubahan pada butir yang disematkan.';
        $deleted = $edited;
        $deleted[$key] = [];
        $unpinned = $edited;
        unset($unpinned[$key][0]['_pin']);
        $before = $this->snapshot();

        foreach (['modified' => $modified, 'deleted' => $deleted, 'pin stripped' => $unpinned] as $case => $payload) {
            $this->postJson($this->url($session, 'accept'), ['stage' => $stage, 'edited' => $payload])
                ->assertUnprocessable();
            $this->assertGeneratorRejected(fn() => $this->generator->acceptStage($session->fresh(), $stage, $payload));
            $this->assertSame($before, $this->snapshot(), $case);
        }
    }

    #[DataProvider('pinnableItems')]
    public function test_manual_stage_save_allows_an_unchanged_pinned_item(string $stage, string $key, string $itemId): void
    {
        $session = $this->manualSession();
        $this->generator->setItemPin($session, $stage, $itemId, true);
        $session->refresh();
        $draft = $session->draf;
        $revision = (int) $session->revisi;

        $this->postJson($this->url($session, 'accept'), ['stage' => $stage, 'edited' => $draft[$stage]])
            ->assertOk();

        $session->refresh();
        $this->assertSame($draft, $session->draf);
        $this->assertTrue($session->draf[$stage][$key][0]['_pin']);
        $this->assertSame($revision + 1, (int) $session->revisi);
        $this->assertSame('edited', $session->status_bagian[$stage]);
        $this->assertTrue($this->generator->readyToCommit($session));
    }

    public static function pinnableItems(): array
    {
        return [
            'CPMK' => ['cpmk', 'cpmk', 'cpmk-1'],
            'Sub-CPMK' => ['sub_cpmk', 'sub_cpmk', 'sub-1'],
            'weekly plan' => ['mingguan', 'minggu', 'week-1'],
            'assessment' => ['penilaian', 'komponen', 'assessment-1'],
        ];
    }

    public function test_manual_stage_save_can_edit_a_sibling_without_changing_the_pinned_item(): void
    {
        $session = $this->manualSession();
        $this->generator->setItemPin($session, 'cpmk', 'cpmk-1', true);
        $draft = $session->fresh()->draf;
        $edited = $draft['cpmk'];
        $edited['cpmk'][] = [
            '_id' => 'cpmk-2',
            'kode' => 'CPMK2',
            'deskripsi' => 'Menilai keamanan terapi obat.',
            'cpl_kode' => ['CPL-1'],
        ];
        $this->generator->acceptStage($session->fresh(), 'cpmk', $edited);
        $revision = (int) $session->fresh()->revisi;
        $edited['cpmk'][1]['deskripsi'] = 'Mengevaluasi keamanan terapi berdasarkan kasus.';

        $this->postJson($this->url($session, 'accept'), ['stage' => 'cpmk', 'edited' => $edited])->assertOk();

        $session->refresh();
        $draft['cpmk'] = $edited;
        $this->assertSame($draft, $session->draf);
        $this->assertTrue($session->draf['cpmk']['cpmk'][0]['_pin']);
        $this->assertSame($revision + 1, (int) $session->revisi);
    }

    public function test_stale_instances_pinning_different_items_preserve_both_updates_and_revisions(): void
    {
        $session = $this->manualSession();
        $edited = $session->draf['cpmk'];
        $edited['cpmk'][] = [
            '_id' => 'cpmk-2',
            'kode' => 'CPMK2',
            'deskripsi' => 'Menilai keamanan terapi obat.',
            'cpl_kode' => ['CPL-1'],
        ];
        $this->generator->acceptStage($session, 'cpmk', $edited);
        // Serial stale models exercise lost-update prevention, not parallel DB locks.
        $first = $session->fresh();
        $second = $session->fresh();
        $revision = (int) $first->revisi;
        $expected = $first->draf;
        $statuses = $first->status_bagian;

        $result = $this->generator->setItemPin($first, 'cpmk', 'cpmk-1', true);
        $this->assertSame($revision + 1, (int) $result->revisi);
        $this->assertSame($revision, (int) $second->revisi);
        $this->assertArrayNotHasKey('_pin', $second->draf['cpmk']['cpmk'][0]);

        $result = $this->generator->setItemPin($second, 'cpmk', 'cpmk-2', true);
        $expected['cpmk']['cpmk'][0]['_pin'] = true;
        $expected['cpmk']['cpmk'][1]['_pin'] = true;
        $this->assertSame($revision + 2, (int) $result->revisi);
        $this->assertSame($expected, $session->fresh()->draf);
        $this->assertSame($statuses, $session->fresh()->status_bagian);

        // Reuse the first stale instance: unpin must retain the sibling's pin too.
        $result = $this->generator->setItemPin($first, 'cpmk', 'cpmk-1', false);
        $expected['cpmk']['cpmk'][0]['_pin'] = false;
        $this->assertSame($revision + 3, (int) $result->revisi);
        $this->assertSame($expected, $session->fresh()->draf);
        $this->assertSame($revision + 3, (int) $session->fresh()->revisi);
    }

    #[DataProvider('committedWrites')]
    public function test_all_generator_write_endpoints_reject_committed_sessions(string $method, string $action): void
    {
        $session = $this->committedSession();
        if ($action === 'unpin') {
            // A real pinned stage ensures rejection isn't merely a no-op.
            $statuses = $session->status_bagian;
            $statuses['cpmk'] = 'pinned';
            $session->update(['status_bagian' => $statuses]);
        }
        $payload = match ($action) {
            'accept' => ['stage' => 'cpmk', 'edited' => ['cpmk' => [$this->replacementCpmk()]]],
            'konteks' => ['bok' => 'Tidak boleh tersimpan.'],
            'item-candidate' => ['stage' => 'cpmk', 'item_id' => 'cpmk-1', 'action' => 'perbaiki_redaksi'],
            'item-apply' => $this->applyPayload((int) $session->revisi),
            'item-pin' => ['stage' => 'cpmk', 'item_id' => 'cpmk-1', 'pinned' => true],
            'commit', '' => [],
            default => ['stage' => 'cpmk'],
        };
        $before = $this->snapshot();

        $this->json($method, $this->url($session, $action), $payload)->assertUnprocessable();

        $this->assertSame($before, $this->snapshot(), "{$method} {$action} mutated committed data");
    }

    public static function committedWrites(): array
    {
        return [
            'generate' => ['POST', 'generate'],
            'accept' => ['POST', 'accept'],
            'reject' => ['POST', 'reject'],
            'pin' => ['POST', 'pin'],
            'unpin' => ['POST', 'unpin'],
            'context' => ['PATCH', 'konteks'],
            'candidate' => ['POST', 'item-candidate'],
            'apply' => ['POST', 'item-apply'],
            'item pin' => ['PATCH', 'item-pin'],
            'double commit' => ['POST', 'commit'],
        ];
    }

    public function test_service_mutators_also_reject_committed_sessions(): void
    {
        $session = $this->committedSession();
        $before = $this->snapshot();
        $actions = [
            fn() => $this->generator->generateStage($session->fresh(), 'cpmk'),
            fn() => $this->generator->acceptStage($session->fresh(), 'cpmk', ['cpmk' => [$this->replacementCpmk()]]),
            fn() => $this->generator->rejectStage($session->fresh(), 'cpmk'),
            fn() => $this->generator->pinStage($session->fresh(), 'cpmk'),
            fn() => $this->generator->unpinStage($session->fresh(), 'cpmk'),
            fn() => $this->generator->regenerateItem($session->fresh(), 'cpmk', 'cpmk-1'),
            fn() => $this->generator->applyItem($session->fresh(), 'cpmk', 'cpmk-1', $this->replacementCpmk(), (int) $session->revisi),
            fn() => $this->generator->setItemPin($session->fresh(), 'cpmk', 'cpmk-1', true),
            fn() => $this->generator->commit($session->fresh()),
        ];
        foreach ($actions as $action) {
            $this->assertGeneratorRejected($action);
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_candidate_from_before_reopen_is_stale_but_current_candidate_can_be_applied(): void
    {
        $session = $this->committedSession();
        $oldRevision = (int) $session->revisi;
        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Buka untuk perbaikan.'])->assertOk();
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'item-apply'), $this->applyPayload($oldRevision))->assertConflict();
        $this->assertSame($before, $this->snapshot());

        $revision = (int) $session->fresh()->revisi;
        $this->postJson($this->url($session, 'item-apply'), $this->applyPayload($revision))->assertOk();
        $session->refresh();
        $this->assertSame($revision + 1, (int) $session->revisi);
        $this->assertSame('cpmk-1', $session->draf['cpmk']['cpmk'][0]['_id']);
        $this->assertSame($this->replacementCpmk()['deskripsi'], $session->draf['cpmk']['cpmk'][0]['deskripsi']);
        // Applying staging changes must not rewrite official children yet.
        $after = $this->snapshot();
        foreach ($this->childTables() as $table) {
            $this->assertSame($before[$table], $after[$table], $table);
        }
    }

    public function test_committed_item_apply_returns_422_even_if_revision_is_stale(): void
    {
        $session = $this->committedSession();
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'item-apply'), $this->applyPayload((int) $session->revisi - 1))
            ->assertUnprocessable();

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('openLinkedStatuses')]
    public function test_submission_is_blocked_until_the_linked_session_is_recommitted(string $status): void
    {
        $session = $this->committedSession();
        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Perbaikan belum dikomit.'])->assertOk();
        if ($status === 'selesai') {
            // Accepting all stages is not equivalent to committing them.
            $this->generator->acceptStage($session->fresh(), 'cpmk');
        }
        $this->assertSame($status, $session->fresh()->status);
        $rps = $session->rpsVersion()->firstOrFail();
        $before = $this->snapshot();

        $this->postJson("/api/v1/rps-versions/{$rps->id}/ajukan", ['catatan' => 'Terlalu awal.'])
            ->assertUnprocessable();
        try {
            app(RpsApprovalService::class)->ajukan($rps->fresh(), $this->actorData());
            $this->fail('Approval service accepted an RPS with open linked staging.');
        } catch (ApprovalException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public static function openLinkedStatuses(): array
    {
        return ['editing' => ['berjalan'], 'all stages accepted' => ['selesai']];
    }

    public function test_recommit_with_changes_creates_a_new_version_and_preserves_earlier_ones(): void
    {
        $session = $this->committedSession('revisi');
        $v1 = $session->rpsVersion()->firstOrFail();
        $this->assertSame(1, (int) $v1->versi);
        $v1WeekIds = $v1->minggu()->pluck('id')->all();
        $v1ComponentIds = $v1->komponenPenilaian()->pluck('id')->all();
        $this->assertNotEmpty($v1WeekIds);

        // Buka → UBAH draf → commit ulang menghasilkan VERSI BARU (v2); v1 utuh.
        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Revisi materi menjadi versi kedua.'])->assertOk();
        $draftV2 = $this->manualDraft();
        $draftV2['mingguan']['minggu'][0]['materi_pustaka'] = 'Materi versi kedua.';
        foreach ($draftV2 as $stage => $edited) {
            $this->postJson($this->url($session, 'accept'), compact('stage', 'edited'))->assertOk();
        }
        $v2Id = $this->postJson($this->url($session, 'commit'))->assertCreated()
            ->assertJsonPath('data.session.status', 'committed')
            ->json('data.rps.id');

        $this->assertNotSame($v1->id, $v2Id);
        $v2 = RpsVersion::findOrFail($v2Id);
        $this->assertSame(2, (int) $v2->versi);
        $this->assertSame($v2->id, (int) $session->fresh()->rps_version_id);
        $this->assertDatabaseCount('rps_version', 2);
        // v1 tetap ada sebagai riwayat dengan anak-anaknya utuh.
        foreach ($v1WeekIds as $id) {
            $this->assertDatabaseHas('rps_minggu', ['id' => $id]);
        }
        foreach ($v1ComponentIds as $id) {
            $this->assertDatabaseHas('komponen_penilaian', ['id' => $id]);
        }
        $this->assertDatabaseHas('rps_minggu', ['rps_version_id' => $v2->id, 'materi_pustaka' => 'Materi versi kedua.']);

        // Buka lagi lalu commit TANPA perubahan → menimpa versi sama (bukan v3).
        $this->postJson($this->url($session, 'reopen'), ['catatan' => 'Buka tanpa mengubah.'])->assertOk();
        $sameId = $this->postJson($this->url($session, 'commit'))->assertCreated()->json('data.rps.id');
        $this->assertSame($v2->id, $sameId);
        $this->assertDatabaseCount('rps_version', 2);
    }

    #[DataProvider('protectedSharedChanges')]
    public function test_older_approved_rps_blocks_new_session_changes_to_shared_outcomes(string $stage, string $key, string $field, mixed $value): void
    {
        $olderSession = $this->committedSession('approved');
        $approved = $olderSession->rpsVersion()->firstOrFail();
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame('setujui', $approved->approvalLogs()->orderByDesc('id')->firstOrFail()->aksi);

        $session = $this->manualSession();
        $this->assertSame($olderSession->mk_id, $session->mk_id);
        $this->assertNull($session->rps_version_id);
        $edited = $session->draf[$stage];
        $edited[$key][0][$field] = $value;
        // Staging can be edited; the course-wide conservative guard is at commit.
        $this->generator->acceptStage($session, $stage, $edited);
        $this->assertFalse($this->generator->readyToCommit($session->fresh()));
        foreach (['cpmk', 'sub_cpmk', 'mingguan', 'penilaian'] as $reviewedStage) {
            $this->generator->acceptStage($session->fresh(), $reviewedStage);
        }
        $this->assertTrue($this->generator->readyToCommit($session->fresh()));
        $before = $this->snapshot();

        $this->postJson($this->url($session, 'commit'))->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        $this->assertGeneratorRejected(fn() => $this->generator->commit($session->fresh()));
        $this->assertSame($before, $this->snapshot());
        $this->assertNull($session->fresh()->rps_version_id);
        $this->assertSame('selesai', $session->fresh()->status);
        $this->assertCommittedGraph($approved->fresh(), 1);
    }

    public static function protectedSharedChanges(): array
    {
        return [
            'CPMK description' => ['cpmk', 'cpmk', 'deskripsi', 'CPMK baru tidak boleh menimpa dokumen lama.'],
            'CPMK CPL mapping' => ['cpmk', 'cpmk', 'cpl_kode', []],
            'Sub-CPMK description' => ['sub_cpmk', 'sub_cpmk', 'deskripsi', 'Sub-CPMK baru tidak boleh menimpa dokumen lama.'],
            'indicator replacement' => ['sub_cpmk', 'sub_cpmk', 'indikator', ['Indikator pengganti yang dilarang.']],
            'indicator removal' => ['sub_cpmk', 'sub_cpmk', 'indikator', []],
        ];
    }

    public function test_unchanged_shared_graph_can_commit_a_second_rps_without_rewriting_the_approved_rps(): void
    {
        $olderSession = $this->committedSession('approved');
        $approved = $olderSession->rpsVersion()->firstOrFail();
        $approvedBefore = $approved->getAttributes();
        $olderSessionBefore = $olderSession->getAttributes();
        $session = $this->manualSession();
        // Only version-owned content changes; no cloning/versioned outcome graph is assumed.
        $week = $session->draf['mingguan'];
        $week['minggu'][0]['materi_pustaka'] = 'Materi khusus RPS kedua.';
        $this->generator->acceptStage($session, 'mingguan', $week);
        $this->assertFalse($this->generator->readyToCommit($session->fresh()));
        $this->postJson($this->url($session, 'commit'))->assertUnprocessable();
        $this->generator->acceptStage($session->fresh(), 'penilaian');
        $before = $this->snapshot();
        $revision = (int) $session->fresh()->revisi;

        $this->postJson($this->url($session, 'commit'))->assertCreated()
            ->assertJsonPath('data.session.status', 'committed');

        $session->refresh();
        $second = $session->rpsVersion()->firstOrFail();
        $this->assertNotSame($approved->id, $second->id);
        $this->assertSame($approved->kode_mk, $second->kode_mk);
        $this->assertGreaterThan((int) $approved->versi, (int) $second->versi);
        $this->assertSame('draft', $second->status);
        $this->assertSame($revision + 1, (int) $session->revisi);
        $this->assertSame($approvedBefore, $approved->fresh()->getAttributes());
        $this->assertSame($olderSessionBefore, $olderSession->fresh()->getAttributes());
        $this->assertDatabaseCount('rps_version', 2);
        $after = $this->snapshot();
        foreach (['cpmk', 'cpmk_cpl', 'sub_cpmk', 'indikator', 'rps_approval_log', 'audit_log', 'notifikasi'] as $table) {
            $this->assertSame($before[$table], $after[$table], $table);
        }
        foreach (['rps_minggu', 'komponen_penilaian', 'rubrik', 'rubrik_kriteria'] as $table) {
            $this->assertDatabaseCount($table, 2);
            $this->assertSame($before[$table], array_slice($after[$table], 0, count($before[$table])), $table);
        }
        $this->assertSame(1, $second->minggu()->count());
        $this->assertSame(1, $second->komponenPenilaian()->count());
        $this->assertSame(
            (int) $approved->minggu()->firstOrFail()->sub_cpmk_id,
            (int) $second->minggu()->firstOrFail()->sub_cpmk_id,
        );
        $this->assertSame(
            (int) $approved->komponenPenilaian()->firstOrFail()->sub_cpmk_id,
            (int) $second->komponenPenilaian()->firstOrFail()->sub_cpmk_id,
        );
        $this->assertDatabaseHas('rps_minggu', ['rps_version_id' => $second->id, 'materi_pustaka' => 'Materi khusus RPS kedua.']);
        Http::assertNothingSent();
    }

    private function manualSession(): GenerateSession
    {
        $session = $this->generator->start($this->course, [
            'user_id' => $this->actor->id,
            'konteks_tambahan' => ['bok' => 'Farmakologi dasar.'],
        ]);
        foreach ($this->manualDraft() as $stage => $edited) {
            $session = $this->generator->acceptStage($session, $stage, $edited);
        }
        $session->update(['catatan_validasi' => ['cpmk' => ['bersih' => true]]]);

        return $session->refresh();
    }

    public function test_generate_mingguan_menyerap_variasi_baris_ujian_model_tanpa_melonggarkan_kontrak(): void
    {
        $session = $this->aiStagedSession();
        // Pola gagal produksi: baris UTS/UAS terpisah menduplikasi pekan belajar,
        // sub_cpmk_kode string kosong alih-alih null.
        $output = json_encode(['minggu' => [
            $this->aiWeek(1, 'Sub-CPMK-1', 'Farmakologi dasar — pengantar reseptor [Pustaka: tidak tersedia dalam konteks]', 30),
            $this->aiWeek(2, 'Sub-CPMK-2', 'Farmakokinetika — absorpsi dan distribusi [Pustaka: tidak tersedia dalam konteks]', 30),
            $this->aiWeek(2, '', 'Evaluasi Tengah Semester (UTS) — cakupan Sub-CPMK-1 s.d. Sub-CPMK-2', 20),
            $this->aiWeek(16, '', 'Evaluasi Akhir Semester (UAS) — seluruh Sub-CPMK', 20),
        ]], JSON_THROW_ON_ERROR);
        $generator = $this->mockAiReturning($output, 1);

        $generator->generateStage($session, 'mingguan');

        $rows = $session->fresh()->draf['mingguan']['minggu'];
        $this->assertSame([1, 2, 8, 16], array_column($rows, 'minggu_ke'));
        $this->assertSame(['Sub-CPMK-1', 'Sub-CPMK-2', null, null], array_map(fn($r) => $r['sub_cpmk_kode'], $rows));
        $this->assertStringContainsString('UTS', $rows[2]['materi_pustaka']);
        $this->assertStringContainsString('UAS', $rows[3]['materi_pustaka']);
    }

    public function test_kontrak_tetap_menolak_pekan_belajar_tanpa_kode_induk_setelah_retry_koreksi(): void
    {
        $session = $this->aiStagedSession();
        // Pekan belajar (bukan ujian) tanpa kode induk: normalisasi tidak boleh meloloskannya.
        $bad = json_encode(['minggu' => [
            $this->aiWeek(1, 'Sub-CPMK-1', 'Farmakologi dasar — pengantar reseptor [Pustaka: tidak tersedia dalam konteks]', 50),
            $this->aiWeek(2, '', 'Farmakokinetika — absorpsi dan distribusi [Pustaka: tidak tersedia dalam konteks]', 50),
        ]], JSON_THROW_ON_ERROR);
        // 1 percobaan awal + auto_revisi_maks(2) retry KOREKSI, semuanya ditolak.
        $generator = $this->mockAiReturning($bad, 3);

        try {
            $generator->generateStage($session, 'mingguan');
            $this->fail('Kontrak meloloskan pekan belajar tanpa kode induk.');
        } catch (GeneratorException $exception) {
            $this->assertStringContainsString('kode induk', $exception->getMessage());
        }
        $this->assertSame('pending', ($session->fresh()->status_bagian ?? [])['mingguan']);
    }

    public function test_baris_ujian_terdeteksi_meski_sinyal_hanya_di_bentuk_luring(): void
    {
        $session = $this->aiStagedSession();
        // Pola gagal produksi: model menaruh label ujian di bentuk_luring, materi
        // hanya berisi cakupan — baris ikut terpetakan ke pekan belajar dan gagal.
        $uts = $this->aiWeek(8, '', 'Materi pekan 1-7 [Pustaka: tidak tersedia dalam konteks]', 20);
        $uts['bentuk_luring'] = 'Ujian Tengah Semester';
        $uas = $this->aiWeek(16, '', 'Seluruh materi semester [Pustaka: tidak tersedia dalam konteks]', 20);
        $uas['bentuk_luring'] = 'Penilaian akhir semester';
        $output = json_encode(['minggu' => [
            $this->aiWeek(1, 'Sub-CPMK-1', 'Farmakologi dasar — pengantar reseptor [Pustaka: tidak tersedia dalam konteks]', 30),
            $this->aiWeek(2, 'Sub-CPMK-2', 'Farmakokinetika — absorpsi dan distribusi [Pustaka: tidak tersedia dalam konteks]', 30),
            $uts,
            $uas,
        ]], JSON_THROW_ON_ERROR);
        $generator = $this->mockAiReturning($output, 1);

        $generator->generateStage($session, 'mingguan');

        $rows = $session->fresh()->draf['mingguan']['minggu'];
        $this->assertSame([1, 2, 8, 16], array_column($rows, 'minggu_ke'));
        $this->assertSame(['Sub-CPMK-1', 'Sub-CPMK-2', null, null], array_map(fn($r) => $r['sub_cpmk_kode'], $rows));
        $this->assertSame('Ujian Tengah Semester', $rows[2]['bentuk_luring']);
        $this->assertSame('Penilaian akhir semester', $rows[3]['bentuk_luring']);
    }

    public function test_generate_penilaian_menskalakan_bobot_komponen_dan_rubrik_ke_100(): void
    {
        $session = $this->aiStagedSession();
        $session = $this->generator->acceptStage($session, 'mingguan', ['minggu' => [
            $this->aiWeek(1, 'Sub-CPMK-1', 'Farmakologi dasar.', 50),
            $this->aiWeek(2, 'Sub-CPMK-2', 'Farmakokinetika.', 50),
        ]]);
        // Pola gagal produksi: "Total bobot_persen komponen harus tepat 100" —
        // selisih aritmetika diskalakan sistem, substansi tetap dari model.
        $output = json_encode(['komponen' => [
            [
                'nama' => 'Kuis farmakodinamika',
                'jenis' => 'kuis',
                'instrumen' => 'Tes objektif',
                'bobot_persen' => 30,
                'sub_cpmk_kode' => 'Sub-CPMK-1',
                'minggu_ke' => 3,
                'rubrik' => null,
            ],
            [
                'nama' => 'Laporan analisis kasus',
                'jenis' => 'tugas',
                'instrumen' => 'Laporan',
                'bobot_persen' => '60',
                'sub_cpmk_kode' => 'Sub-CPMK-2',
                'minggu_ke' => 6,
                'rubrik' => [
                    'jenis' => 'analitik',
                    'jumlah_level_skala' => 2,
                    'label_skala' => ['Kurang', 'Baik'],
                    'kriteria' => [
                        ['kriteria' => 'Ketepatan analisis', 'bobot' => 40, 'deskriptor' => ['Analisis belum tepat.', 'Analisis tepat.']],
                        ['kriteria' => 'Kelengkapan data', 'bobot' => '40', 'deskriptor' => ['Data belum lengkap.', 'Data lengkap.']],
                    ],
                ],
            ],
        ]], JSON_THROW_ON_ERROR);
        $generator = $this->mockAiReturning($output, 1);

        $generator->generateStage($session, 'penilaian');

        $rows = $session->fresh()->draf['penilaian']['komponen'];
        $this->assertEqualsWithDelta(100, array_sum(array_column($rows, 'bobot_persen')), 0.001);
        $this->assertEqualsWithDelta(33.33, $rows[0]['bobot_persen'], 0.001);
        $this->assertEquals([50, 50], array_column($rows[1]['rubrik']['kriteria'], 'bobot'));
    }

    public function test_matriks_cpl_terbaca_saat_institusi_mk_beda_dari_kurikulum(): void
    {
        // Reproduksi bug produksi (MK 1501 FF 344): MK di institusi Fakultas,
        // kurikulum di Prodi, matriks mk_cpl tersimpan dgn institusi_id kurikulum.
        // Generator WAJIB tetap mendeteksi pemetaan (tak melempar "belum dipetakan").
        $fakultas = Institusi::create(['kode' => 'FAK-MM', 'nama' => 'Fakultas MM', 'jenis' => 'fakultas']);
        $prodi = Institusi::create(['kode' => 'PRODI-MM', 'nama' => 'Prodi MM', 'jenis' => 'prodi', 'parent_id' => $fakultas->id]);
        $kur = Kurikulum::create(['institusi_id' => $prodi->id, 'kode' => 'KUR-MM', 'nama' => 'Kur MM', 'tahun' => '2026']);
        $mk = MataKuliah::create([
            'institusi_id' => $fakultas->id, // BEDA dari kurikulum (prodi)
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-MISMATCH',
            'nama' => 'MK Beda Institusi',
            'jenis_mk' => 'murni',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 1,
        ]);
        $cpl = Cpl::create(['institusi_id' => $prodi->id, 'kurikulum_id' => $kur->id, 'kode' => 'CPL-1', 'deskripsi' => 'Deskripsi CPL.']);
        MkCpl::create(['institusi_id' => $prodi->id, 'kode_mk' => $mk->kode_mk, 'cpl_id' => $cpl->id, 'bobot' => 100]);

        $session = $this->generator->start($mk, ['user_id' => $this->actor->id]);
        $cpmkJson = json_encode(['cpmk' => [[
            'kode' => 'CPMK1',
            'deskripsi' => 'Menganalisis konsep dasar.',
            'cpl_kode' => ['CPL-1'],
            'taksonomi_kode' => ['C4'],
        ]]], JSON_THROW_ON_ERROR);
        $generator = $this->mockAiReturning($cpmkJson, 1);

        $generator->generateStage($session, 'cpmk'); // tak boleh melempar "belum dipetakan"

        $rows = $session->fresh()->draf['cpmk']['cpmk'];
        $this->assertCount(1, $rows);
        $this->assertSame('CPMK1', $rows[0]['kode']);
    }

    private function aiStagedSession(): GenerateSession
    {
        $session = $this->generator->start($this->course, [
            'user_id' => $this->actor->id,
            'konteks_tambahan' => ['bok' => 'Farmakologi dasar.'],
        ]);
        $session = $this->generator->acceptStage($session, 'cpmk', ['cpmk' => [[
            'kode' => 'CPMK1',
            'deskripsi' => 'Menganalisis mekanisme kerja obat.',
            'cpl_kode' => ['CPL-1'],
            'taksonomi_kode' => ['C4'],
        ]]]);
        $session = $this->generator->acceptStage($session, 'sub_cpmk', ['sub_cpmk' => [
            [
                'kode' => 'Sub-CPMK-1',
                'cpmk_kode' => 'CPMK1',
                'deskripsi' => 'Menjelaskan interaksi obat-reseptor.',
                'taksonomi_kode' => ['C2'],
                'indikator' => ['Ketepatan menjelaskan interaksi obat-reseptor.', 'Kelengkapan contoh interaksi.'],
            ],
            [
                'kode' => 'Sub-CPMK-2',
                'cpmk_kode' => 'CPMK1',
                'deskripsi' => 'Menganalisis profil farmakokinetika obat.',
                'taksonomi_kode' => ['C4'],
                'indikator' => ['Ketepatan analisis parameter farmakokinetika.', 'Ketepatan interpretasi kurva kadar-waktu.'],
            ],
        ]]);

        return $session->refresh();
    }

    private function aiWeek(int $week, string $kode, string $materi, float $bobot): array
    {
        return [
            'minggu_ke' => $week,
            'sub_cpmk_kode' => $kode,
            'indikator' => 'Ketepatan capaian sesuai target pekan.',
            'kriteria_penilaian' => "Kriteria: ketepatan analisis.\nTeknik: tes tertulis.",
            'metode_pembelajaran' => 'Diskusi kasus',
            'bentuk_luring' => 'Tatap muka',
            'bentuk_daring' => null,
            'pengalaman_belajar' => 'Menganalisis kasus terkait materi pekan.',
            'materi_pustaka' => $materi,
            'bobot_penilaian' => $bobot,
        ];
    }

    /** Ganti mock setUp lalu resolve generator baru agar memakai mock ini. */
    private function mockAiReturning(string $text, int $times): RpsGeneratorService
    {
        $outcome = new AiOutcome(new LlmResult(
            text: $text,
            inputTokens: 500,
            outputTokens: 400,
            modelVersion: 'unit-test-model',
        ), 0.001);
        $this->mock(AiService::class, function (MockInterface $mock) use ($outcome, $times): void {
            $mock->shouldReceive('run')->times($times)->andReturn($outcome);
        });

        return app(RpsGeneratorService::class);
    }

    private function committedSession(string $status = 'draft'): GenerateSession
    {
        $session = $this->manualSession();
        $rps = $this->generator->commit($session);
        $approval = app(RpsApprovalService::class);
        if (in_array($status, ['review', 'revisi', 'approved'], true)) {
            $rps = $approval->ajukan($rps, $this->actorData());
        }
        if ($status === 'revisi') {
            $approval->mintaRevisi($rps, $this->actorData());
        }
        if ($status === 'approved') {
            $approval->setujui($rps, $this->actorData());
        }

        return $session->refresh();
    }

    private function manualDraft(): array
    {
        return [
            'cpmk' => ['cpmk' => [[
                '_id' => 'cpmk-1',
                'kode' => 'CPMK1',
                'deskripsi' => 'Menganalisis mekanisme kerja obat.',
                'cpl_kode' => ['CPL-1'],
            ]]],
            'sub_cpmk' => ['sub_cpmk' => [[
                '_id' => 'sub-1',
                'kode' => 'Sub-CPMK1.1',
                'cpmk_kode' => 'CPMK1',
                'deskripsi' => 'Menjelaskan hubungan dosis dan respons.',
                'indikator' => ['Ketepatan analisis hubungan dosis dan respons.'],
            ]]],
            'mingguan' => ['minggu' => [[
                '_id' => 'week-1',
                'minggu_ke' => 1,
                'sub_cpmk_kode' => 'Sub-CPMK1.1',
                'indikator' => 'Ketepatan analisis dosis.',
                'kriteria_penilaian' => 'Rubrik analitik.',
                'metode_pembelajaran' => 'Diskusi kasus',
                'bentuk_luring' => 'Tatap muka',
                'pengalaman_belajar' => 'Menganalisis kasus sederhana.',
                'materi_pustaka' => 'Materi farmakologi dasar.',
                'bobot_penilaian' => 100,
            ]]],
            'penilaian' => ['komponen' => [[
                '_id' => 'assessment-1',
                'nama' => 'Tugas analisis',
                'jenis' => 'tugas',
                'instrumen' => 'Laporan kasus',
                'sub_cpmk_kode' => 'Sub-CPMK1.1',
                'minggu_ke' => 1,
                'bobot_persen' => 100,
                'rubrik' => [
                    'jenis' => 'analitik',
                    'jumlah_level_skala' => 2,
                    'label_skala' => ['Perlu perbaikan', 'Baik'],
                    'kriteria' => [[
                        'kriteria' => 'Ketepatan analisis',
                        'bobot' => 100,
                        'deskriptor' => ['Analisis belum tepat.', 'Analisis tepat dan beralasan.'],
                    ]],
                ],
            ]]],
        ];
    }

    private function replacementCpmk(): array
    {
        return [
            'kode' => 'CPMK1',
            'deskripsi' => 'Menganalisis mekanisme dan keamanan obat.',
            'cpl_kode' => ['CPL-1'],
        ];
    }

    private function applyPayload(int $revision): array
    {
        return [
            'stage' => 'cpmk',
            'item_id' => 'cpmk-1',
            'after' => $this->replacementCpmk(),
            'base_revisi' => $revision,
        ];
    }

    private function actorData(): array
    {
        return ['id' => $this->actor->id, 'nama' => $this->actor->name, 'catatan' => 'Perbaiki indikator dan penilaian.'];
    }

    private function url(GenerateSession $session, string $action): string
    {
        return "/api/v1/generate-sessions/{$session->id}" . ($action === '' ? '' : "/{$action}");
    }

    private function assertGeneratorRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Generator service accepted a forbidden lifecycle transition.');
        } catch (GeneratorException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }

    private function assertCommittedGraph(RpsVersion $rps, int $childCount): void
    {
        $this->assertDatabaseCount('rps_version', 1);
        foreach (['cpmk', 'cpmk_cpl', 'sub_cpmk', 'indikator'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        foreach (['rps_minggu', 'komponen_penilaian', 'rubrik', 'rubrik_kriteria'] as $table) {
            $this->assertDatabaseCount($table, $childCount);
        }
        $this->assertSame($childCount, $rps->minggu()->count());
        $this->assertSame($childCount, $rps->komponenPenilaian()->count());
        $subId = DB::table('sub_cpmk')->value('id');
        foreach ($rps->minggu()->get() as $week) {
            $this->assertSame((int) $subId, (int) $week->sub_cpmk_id);
            $this->assertNotEmpty($week->estimasi_waktu);
        }
        foreach ($rps->komponenPenilaian()->get() as $component) {
            $this->assertSame((int) $subId, (int) $component->sub_cpmk_id);
            $rubric = DB::table('rubrik')->where('komponen_penilaian_id', $component->id)->sole();
            $this->assertSame(1, DB::table('rubrik_kriteria')->where('rubrik_id', $rubric->id)->count());
        }
        $this->assertEquals(100, $rps->komponenPenilaian()->sum('bobot_persen'));
    }

    private function childTables(): array
    {
        return ['cpmk', 'cpmk_cpl', 'sub_cpmk', 'indikator', 'rps_minggu', 'komponen_penilaian', 'rubrik', 'rubrik_kriteria'];
    }

    /** Raw, ordered rows include IDs/timestamps so silent rewrites also fail. */
    private function snapshot(): array
    {
        $snapshot = [];
        foreach (array_merge($this->childTables(), ['generate_session', 'rps_version', 'rps_approval_log', 'audit_log', 'notifikasi']) as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()
                ->map(static fn(object $row): array => (array) $row)->all();
        }

        return $snapshot;
    }
}
