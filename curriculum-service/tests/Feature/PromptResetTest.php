<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Institusi;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Ai\PromptRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromptResetTest extends TestCase
{
    use RefreshDatabase;

    private Institusi $tenant;
    private Institusi $other;
    private User $actor;
    private PromptRepository $prompts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Institusi::create(['kode' => 'PROMPT-A', 'nama' => 'Prompt A', 'jenis' => 'prodi']);
        $this->other = Institusi::create(['kode' => 'PROMPT-B', 'nama' => 'Prompt B', 'jenis' => 'prodi']);
        $this->actor = User::factory()->create(['institusi_id' => $this->tenant->id]);
        Sanctum::actingAs($this->actor);
        $this->prompts = app(PromptRepository::class);
    }

    public function test_reset_inherited_global_prompt_pins_each_selected_context_to_live_code_defaults(): void
    {
        $global = $this->template(null, null, ['versi' => 20]);
        $theory = $this->template(null, 'murni', ['versi' => 30]);
        $practical = $this->template(null, 'praktikum', ['versi' => 40]);

        foreach ([null, 'murni', 'praktikum'] as $jenis) {
            $response = $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk', 'jenis_mk' => $jenis])
                ->assertOk()->assertJsonPath('data.use_default', true)
                ->assertJsonPath('data.institusi_id', $this->tenant->id)
                ->assertJsonPath('data.jenis_mk', $jenis);
            $this->assertDefault('cpmk', $this->tenant->id, $jenis);
            $this->assertSame($response->json('data.id'), $this->prompts->resolve('cpmk', $this->tenant->id, $jenis)['template_id']);
        }

        // No copied default snapshot: the same repository follows changed config immediately.
        config([
            'prompts.slots.cpmk.system' => 'Default sistem baru setelah deployment.',
            'prompts.slots.cpmk.schema' => '{"cpmk":[],"revision":"new"}'
        ]);
        foreach ([null, 'murni', 'praktikum'] as $jenis) {
            $this->assertDefault('cpmk', $this->tenant->id, $jenis);
            $catalog = $this->catalogSlot('cpmk', $jenis);
            $this->assertSame('Default sistem baru setelah deployment.', $catalog['effective_system']);
            $this->assertSame('{"cpmk":[],"revision":"new"}', $catalog['effective_schema']);
            $this->assertNull($catalog['override']);
            $this->assertTrue($catalog['selection']['use_default']);
            $this->assertFalse($catalog['can_edit']);
        }
        foreach ([$global, $theory, $practical] as $untouched) {
            $this->assertTrue($untouched->fresh()->aktif);
            $this->assertFalse($untouched->fresh()->use_default);
        }
    }

    public function test_reset_multiple_versions_changes_only_the_exact_tenant_slot_and_type(): void
    {
        $global = $this->template(null, 'praktikum', ['versi' => 99]);
        $generic = $this->template($this->tenant->id, null, ['versi' => 90]);
        $theory = $this->template($this->tenant->id, 'murni');
        $foreign = $this->template($this->other->id, 'praktikum');
        $anotherSlot = $this->template($this->tenant->id, 'praktikum', ['jenis_output' => 'sub_cpmk']);
        $old = $this->template($this->tenant->id, 'praktikum', ['versi' => 3]);
        $latest = $this->template($this->tenant->id, 'praktikum', ['versi' => 4]);
        $inactive = $this->template($this->tenant->id, 'praktikum', ['versi' => 10, 'aktif' => false]);
        $before = collect([$global, $generic, $theory, $foreign, $anotherSlot])
            ->mapWithKeys(fn($row) => [$row->id => $row->fresh()->getAttributes()]);

        $response = $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk', 'jenis_mk' => 'praktikum'])
            ->assertOk()->assertJsonPath('data.versi', 11);
        $this->assertDefault('cpmk', $this->tenant->id, 'praktikum');
        $this->assertDatabaseCount('prompt_template', 9);
        foreach ([$old, $latest, $inactive] as $historical) {
            $this->assertFalse($historical->fresh()->aktif);
            $this->assertSame($historical->sistem_prompt, $historical->fresh()->sistem_prompt);
        }
        foreach ($before as $id => $attributes) {
            $this->assertSame($attributes, PromptTemplate::findOrFail($id)->getAttributes());
        }
        $this->assertSame($theory->id, $this->prompts->resolve('cpmk', $this->tenant->id, 'murni')['template_id']);
        $this->assertSame($foreign->id, $this->prompts->resolve('cpmk', $this->other->id, 'praktikum')['template_id']);
        $this->assertSame($generic->id, $this->prompts->resolve('cpmk', $this->tenant->id)['template_id']);

        // Repeated submissions still yield exactly one active marker, not duplicate winners.
        $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk', 'jenis_mk' => 'praktikum'])
            ->assertOk()->assertJsonPath('data.versi', 12);
        $this->assertFalse(PromptTemplate::findOrFail($response->json('data.id'))->aktif);
        $this->assertSame(1, PromptTemplate::where('institusi_id', $this->tenant->id)
            ->where('jenis_output', 'cpmk')->where('jenis_mk', 'praktikum')->where('aktif', true)->count());
    }

    public function test_reset_logs_the_authenticated_actor_not_submitted_actor_fields(): void
    {
        $this->postJson('/api/v1/prompts/reset', [
            'slot' => 'cpmk',
            'actor_id' => 999999,
            'actor_nama' => 'Forged actor',
        ])->assertOk();

        $audit = AuditLog::where('action', 'prompt.reset')->sole();
        $this->assertEquals($this->actor->id, $audit->user_id);
        $this->assertEquals($this->tenant->id, $audit->institusi_id);
        $this->assertSame($this->actor->name, $audit->meta['actor_nama']);
        $this->assertSame('cpmk', $audit->meta['slot']);
        $this->assertTrue(PromptTemplate::findOrFail($audit->entity_id)->use_default);
    }

    public function test_create_and_edit_after_reset_supersede_marker_and_reject_stale_versions(): void
    {
        $old = $this->template($this->tenant->id, 'murni', ['versi' => 4]);
        $markerId = $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk', 'jenis_mk' => 'murni'])
            ->assertOk()->json('data.id');
        $this->putJson("/api/v1/prompt-templates/{$old->id}", ['sistem_prompt' => 'Tidak boleh menimpa reset.'])->assertConflict();
        $this->putJson("/api/v1/prompt-templates/{$markerId}", ['sistem_prompt' => 'Tidak boleh mengedit marker.'])->assertConflict();
        $this->deleteJson("/api/v1/prompt-templates/{$old->id}")->assertConflict();
        $this->deleteJson("/api/v1/prompt-templates/{$markerId}")->assertConflict();

        $createdId = $this->postJson('/api/v1/prompt-templates', [
            'jenis_output' => 'cpmk',
            'jenis_mk' => 'murni',
            'sistem_prompt' => 'Override baru setelah reset.',
            'skema_output' => '',
        ])->assertCreated()->assertJsonPath('data.versi', 6)
            ->assertJsonPath('data.use_default', false)->json('data.id');
        $this->assertFalse(PromptTemplate::findOrFail($markerId)->aktif);
        $this->assertSame('Override baru setelah reset.', $this->prompts->resolve('cpmk', $this->tenant->id, 'murni')['system']);

        $editedId = $this->putJson("/api/v1/prompt-templates/{$createdId}", [
            'sistem_prompt' => 'Perubahan berikutnya menjadi versi baru.',
            'skema_output' => '{"cpmk":[]}',
        ])->assertOk()->assertJsonPath('data.versi', 7)->json('data.id');
        $this->assertNotSame($createdId, $editedId);
        $this->assertFalse(PromptTemplate::findOrFail($createdId)->aktif);
        $this->assertSame($editedId, $this->prompts->resolve('cpmk', $this->tenant->id, 'murni')['template_id']);
        $this->putJson("/api/v1/prompt-templates/{$createdId}", ['aktif' => true])->assertConflict();

        // Legacy DELETE is non-destructive and cannot expose global/older fallback either.
        $this->deleteJson("/api/v1/prompt-templates/{$editedId}")->assertOk();
        $this->assertDatabaseHas('prompt_template', ['id' => $editedId, 'aktif' => false]);
        $this->assertDefault('cpmk', $this->tenant->id, 'murni');
    }

    public function test_duplicate_legacy_versions_have_deterministic_winner_and_edits_do_not_mutate_history(): void
    {
        $older = $this->template($this->tenant->id, null, ['versi' => 8]);
        $winner = $this->template($this->tenant->id, null, ['versi' => 8]);
        $this->assertSame($winner->id, $this->prompts->resolve('cpmk', $this->tenant->id)['template_id']);
        $this->putJson("/api/v1/prompt-templates/{$older->id}", ['sistem_prompt' => 'Stale duplicate update.'])->assertConflict();
        $new = $this->putJson("/api/v1/prompt-templates/{$winner->id}", ['sistem_prompt' => 'Valid latest duplicate update.'])
            ->assertOk()->assertJsonPath('data.versi', 9)->json('data.id');
        $this->assertSame($new, $this->prompts->resolve('cpmk', $this->tenant->id)['template_id']);
        $this->assertFalse($older->fresh()->aktif);
        $this->assertFalse($winner->fresh()->aktif);
    }

    public function test_catalog_matches_runtime_for_generic_theory_practical_and_empty_schema_fallback(): void
    {
        $generic = $this->template($this->tenant->id, null, ['versi' => 99]);
        $theory = $this->template($this->tenant->id, 'murni', ['skema_output' => null]);
        $practical = $this->template($this->tenant->id, 'praktikum', ['skema_output' => ['cpmk' => []]]);
        foreach ([[$generic, null], [$theory, 'murni'], [$practical, 'praktikum']] as [$template, $jenis]) {
            $slot = $this->catalogSlot('cpmk', $jenis);
            $runtime = $this->prompts->resolve('cpmk', $this->tenant->id, $jenis);
            $this->assertSame($template->id, $slot['override']['id']);
            $this->assertSame($runtime['system'], $slot['effective_system']);
            $this->assertSame($runtime['schema'], $slot['effective_schema']);
            $this->assertSame($runtime['sumber'], $slot['sumber_efektif']);
            $this->assertSame($jenis, $slot['jenis_mk']);
            $this->assertTrue($slot['can_edit']);
        }
        $this->assertSame(config('prompts.slots.cpmk.schema'), $this->catalogSlot('cpmk', 'murni')['effective_schema']);
    }

    public function test_inherited_global_and_generic_overrides_are_visible_but_not_editable_in_selected_context(): void
    {
        $global = $this->template(null, 'praktikum');
        $slot = $this->catalogSlot('cpmk', 'praktikum');
        $this->assertSame($global->id, $slot['override']['id']);
        $this->assertFalse($slot['can_edit']);

        $generic = $this->template($this->tenant->id, null);
        $slot = $this->catalogSlot('cpmk', 'praktikum');
        $this->assertSame($generic->id, $slot['override']['id']);
        $this->assertFalse($slot['can_edit']);
    }

    public function test_tenant_cannot_reset_another_tenant_or_mutate_global_and_foreign_overrides(): void
    {
        $foreign = $this->template($this->other->id, null);
        $global = $this->template(null, null);
        $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk', 'institusi_id' => $this->other->id])->assertForbidden();
        $this->getJson('/api/v1/prompts/catalog?institusi_id=' . $this->other->id)->assertForbidden();
        $this->postJson('/api/v1/prompt-templates', [
            'jenis_output' => 'cpmk',
            'sistem_prompt' => 'Unauthorized foreign override.',
            'institusi_id' => $this->other->id,
        ])->assertForbidden();
        foreach ([$foreign, $global] as $protected) {
            $this->putJson("/api/v1/prompt-templates/{$protected->id}", ['sistem_prompt' => 'Unauthorized protected update.'])->assertForbidden();
            $this->deleteJson("/api/v1/prompt-templates/{$protected->id}")->assertForbidden();
            $this->assertTrue($protected->fresh()->aktif);
        }
        $this->assertDatabaseCount('prompt_template', 2);

        // Explicit null cannot escape middleware/auth tenant ownership.
        $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk', 'institusi_id' => null])
            ->assertOk()->assertJsonPath('data.institusi_id', $this->tenant->id);
        $this->assertTrue($global->fresh()->aktif);
        $this->assertTrue($foreign->fresh()->aktif);
    }

    public function test_global_reset_works_in_empty_scope_and_preserves_tenant_and_type_records(): void
    {
        Sanctum::actingAs(User::factory()->create(['institusi_id' => null]));
        // A stable global lock row exists even with no prompt rows.
        $this->assertDatabaseHas('prompt_template_locks', ['id' => 1]);
        $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk'])
            ->assertOk()->assertJsonPath('data.institusi_id', null)->assertJsonPath('data.versi', 1);
        $own = $this->template($this->tenant->id, null);
        $typed = $this->template(null, 'murni');
        $this->postJson('/api/v1/prompts/reset', ['slot' => 'cpmk'])->assertOk()->assertJsonPath('data.versi', 2);
        $this->assertDefault('cpmk', null, null);
        $this->assertTrue($own->fresh()->aktif);
        $this->assertTrue($typed->fresh()->aktif);
        $this->assertSame($typed->id, $this->prompts->resolve('cpmk', null, 'murni')['template_id']);
        $this->assertSame($own->id, $this->prompts->resolve('cpmk', $this->tenant->id)['template_id']);
    }

    public function test_invalid_slot_type_and_schema_are_422_for_create_update_reset_and_catalog(): void
    {
        foreach ([[], ['slot' => 'unknown'], ['slot' => 'cpmk', 'jenis_mk' => 'invalid']] as $payload) {
            $this->postJson('/api/v1/prompts/reset', $payload)->assertUnprocessable();
        }
        $this->getJson('/api/v1/prompts/catalog?jenis_mk=invalid')->assertUnprocessable();
        $this->postJson('/api/v1/prompt-templates', ['jenis_output' => 'unknown', 'sistem_prompt' => 'Unknown slot prompt.'])->assertUnprocessable();
        $this->postJson('/api/v1/prompt-templates', ['jenis_output' => 'cpmk', 'jenis_mk' => 'invalid', 'sistem_prompt' => 'Invalid type prompt.'])->assertUnprocessable();

        $current = $this->template($this->tenant->id, null);
        foreach (['not JSON', 'null', 'true', '42', '"scalar"', '[]', '{}', '{"wrong":[]}', '{"cpmk":"wrong"}', '{"cpmk":{}}'] as $schema) {
            $this->postJson('/api/v1/prompt-templates', [
                'jenis_output' => 'cpmk',
                'sistem_prompt' => 'Invalid schema prompt.',
                'skema_output' => $schema,
            ])->assertUnprocessable()->assertJsonValidationErrors('skema_output');
            $this->putJson("/api/v1/prompt-templates/{$current->id}", ['skema_output' => $schema])
                ->assertUnprocessable()->assertJsonValidationErrors('skema_output');
        }
        $this->putJson("/api/v1/prompt-templates/{$current->id}", ['jenis_output' => 'unknown'])->assertUnprocessable();
        $this->putJson("/api/v1/prompt-templates/{$current->id}", ['jenis_mk' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('prompt_template', 1);
        $this->assertTrue($current->fresh()->aktif);
    }

    public function test_schema_checks_all_default_root_keys_and_rejects_client_version_marker_or_scope_move(): void
    {
        $this->postJson('/api/v1/prompt-templates', [
            'jenis_output' => 'buku_naratif',
            'sistem_prompt' => 'Incomplete root keys.',
            'skema_output' => '{"pengantar":"..."}',
        ])->assertUnprocessable()->assertJsonValidationErrors('skema_output');
        foreach ([['versi' => 999], ['use_default' => true]] as $invalid) {
            $this->postJson('/api/v1/prompt-templates', $invalid + [
                'jenis_output' => 'cpmk',
                'sistem_prompt' => 'Client-controlled version marker.',
            ])->assertUnprocessable();
        }
        $current = $this->template($this->tenant->id, null);
        foreach ([['jenis_output' => 'chat'], ['jenis_mk' => 'murni']] as $move) {
            $this->putJson("/api/v1/prompt-templates/{$current->id}", $move)->assertUnprocessable();
        }
        $this->assertTrue($current->fresh()->aktif);
    }

    public function test_empty_json_schema_falls_back_and_chat_accepts_empty_schema(): void
    {
        foreach (['', null, '   '] as $schema) {
            $this->postJson('/api/v1/prompt-templates', [
                'jenis_output' => 'cpmk',
                'sistem_prompt' => 'Default schema is inherited.',
                'skema_output' => $schema,
            ])->assertCreated()->assertJsonPath('data.skema_output', null);
            $this->assertSame(config('prompts.slots.cpmk.schema'), $this->catalogSlot('cpmk')['effective_schema']);
        }
        $this->postJson('/api/v1/prompt-templates', [
            'jenis_output' => 'chat',
            'sistem_prompt' => 'Balas dalam teks bebas.',
            'skema_output' => '',
        ])->assertCreated();
        $slot = $this->catalogSlot('chat');
        $this->assertSame('', $slot['effective_schema']);
        $this->assertSame('Balas dalam teks bebas.', $slot['effective_system']);
    }

    private function template(?int $tenantId, ?string $jenis, array $attributes = []): PromptTemplate
    {
        return PromptTemplate::create(array_replace([
            'institusi_id' => $tenantId,
            'jenis_output' => 'cpmk',
            'jenis_mk' => $jenis,
            'sistem_prompt' => 'Prompt override untuk pengujian.',
            'skema_output' => null,
            'versi' => 1,
            'aktif' => true,
            'use_default' => false,
        ], $attributes));
    }

    private function assertDefault(string $slot, ?int $tenantId, ?string $jenis): void
    {
        $resolved = $this->prompts->resolve($slot, $tenantId, $jenis);
        $this->assertSame('default', $resolved['sumber']);
        $this->assertSame(config("prompts.slots.{$slot}.system"), $resolved['system']);
        $this->assertSame(config("prompts.slots.{$slot}.schema"), $resolved['schema']);
    }

    private function catalogSlot(string $slot, ?string $jenis = null): array
    {
        $rows = $this->getJson('/api/v1/prompts/catalog' . ($jenis ? '?jenis_mk=' . $jenis : ''))
            ->assertOk()->json('data');
        return collect($rows)->firstWhere('slot', $slot);
    }
}
