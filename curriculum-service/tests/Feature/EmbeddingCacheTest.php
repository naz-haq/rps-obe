<?php

namespace Tests\Feature;

use App\Models\AiInteraksi;
use App\Models\AiKredensial;
use App\Models\AiPengaturan;
use App\Models\DokumenChunk;
use App\Models\DokumenRujukan;
use App\Models\Institusi;
use App\Models\MkDokumenRujukan;
use App\Services\Ai\EmbeddingService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmbeddingCacheTest extends TestCase
{
    use RefreshDatabase;

    private Institusi $tenant;
    private EmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'cache.default' => 'array',
            'ai.fallback_to_mock' => false,
            'ai.embedding' => [
                'provider' => 'openai',
                'model' => 'embedding-test',
                'dimensions' => 3,
                'pricing' => ['input' => 0.02],
            ],
            'ai.providers.openai.api_key' => 'fake-server-secret',
            'ai.providers.openai.base_url' => 'https://embedding.test/v1',
            'ai.providers.nvidia.api_key' => 'fake-nvidia-secret',
            'ai.providers.nvidia.base_url' => 'https://nvidia.test/v1',
            'ai.embedding_cache_ttl' => 86400,
            'ai.embedding_retrieval_cache_ttl' => 300,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        $this->tenant = Institusi::create(['nama' => 'Embedding tenant']);
        $this->service = app(EmbeddingService::class);
    }

    private function context(array $extra = []): array
    {
        return array_replace(['institusi_id' => $this->tenant->id], $extra);
    }

    private function response(array $vector = [1, 0, 0]): array
    {
        return ['data' => [['embedding' => $vector]], 'usage' => ['prompt_tokens' => 100]];
    }

    private function fakeVectors(): void
    {
        Http::fake(fn(Request $request) => Http::response($this->response(
            array_pad([1], (int) ($request['dimensions'] ?? config('ai.embedding.dimensions')), 0)
        )));
    }

    private function chunk(string $text = 'Passage', ?Institusi $tenant = null): DokumenChunk
    {
        $document = DokumenRujukan::create([
            'institusi_id' => ($tenant ?? $this->tenant)->id,
            'jenis' => 'buku',
            'judul' => $text,
            'sumber_konten' => true,
        ]);

        return DokumenChunk::create(['dokumen_id' => $document->id, 'teks' => $text]);
    }

    private function search(array $opts = [], int $topK = 5): array
    {
        return $this->service->search($this->tenant->id, 'Query', $topK, $opts);
    }

    private function retrievalEntries(): array
    {
        return array_filter(
            $this->arrayStore()->all(),
            fn($key) => str_starts_with($key, 'ai:retrieval:'),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function arrayStore(): ArrayStore
    {
        $store = Cache::store()->getStore();
        $this->assertInstanceOf(ArrayStore::class, $store);
        /** @var ArrayStore $store */
        return $store;
    }

    public function test_identical_text_is_paid_once_and_cache_hits_have_zero_usage(): void
    {
        $this->fakeVectors();
        $first = $this->service->embed('Exact text', $this->context());
        $second = $this->service->embed('Exact text', $this->context());

        Http::assertSentCount(1);
        $this->assertFalse($first['cache_hit']);
        $this->assertSame(100, $first['tokens']);
        $this->assertGreaterThan(0, $first['biaya']);
        $this->assertTrue($second['cache_hit']);
        $this->assertSame(0, $second['tokens']);
        $this->assertSame(0.0, $second['biaya']);
        $this->assertSame($first['embedding'], $second['embedding']);
        $this->assertSame(1, AiInteraksi::count());
        $this->assertSame(100, (int) AiInteraksi::sum('tokens_in'));
        $serialized = json_encode($this->arrayStore()->all());
        $this->assertStringNotContainsString('fake-server-secret', $serialized);
        $this->assertStringNotContainsString('Exact text', $serialized);
    }

    public function test_cache_key_separates_tenant_user_provider_model_endpoint_dimensions_type_and_exact_text(): void
    {
        $this->fakeVectors();
        $other = Institusi::create(['nama' => 'Other']);
        $this->service->embed('Text', $this->context());
        $this->service->embed('Text', ['institusi_id' => $other->id]);
        $this->service->embed('Text', $this->context(['user_id' => 11]));
        $this->service->embed('Text', $this->context(['input_type' => 'passage']));
        $this->service->embed('Text ', $this->context());
        config()->set('ai.embedding.model', 'other-model');
        $this->service->embed('Text', $this->context());
        config()->set('ai.embedding.dimensions', 4);
        $this->service->embed('Text', $this->context());
        config()->set('ai.providers.openai.base_url', 'https://other.test/v1');
        $this->service->embed('Text', $this->context());
        config()->set('ai.embedding.provider', 'nvidia');
        $this->service->embed('Text', $this->context());
        $hit = $this->service->embed('Text', $this->context());

        Http::assertSentCount(9);
        $this->assertTrue($hit['cache_hit']);
        Http::assertSent(fn(Request $r) => $r->url() === 'https://nvidia.test/v1/embeddings'
            && $r['input_type'] === 'query' && $r['input'] === ['Text'] && ! isset($r['dimensions']));
    }

    public function test_tenant_settings_and_user_credentials_are_resolved_before_cache(): void
    {
        $this->fakeVectors();
        $credential = AiKredensial::create([
            'institusi_id' => $this->tenant->id,
            'user_id' => 12,
            'provider' => 'openai',
            'api_key_encrypted' => 'fake-user-secret',
            'aktif' => true,
        ]);
        $this->service->embed('Text', $this->context(['user_id' => 12]));
        Http::assertSent(fn(Request $r) => $r->hasHeader('Authorization', 'Bearer fake-user-secret'));
        AiPengaturan::create([
            'institusi_id' => $this->tenant->id,
            'embedding_provider' => 'openai',
            'embedding_model' => 'tenant-model',
            'embedding_dimensions' => 3,
        ]);
        $new = $this->service->embed('Text', $this->context(['user_id' => 12]));
        $this->assertSame('tenant-model', $new['model']);
        Http::assertSentCount(2);

        $credential->update(['aktif' => false]);
        config()->set('ai.providers.openai.api_key', null);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada kredensial');
        $this->service->embed('Text', $this->context(['user_id' => 12]));
    }

    public function test_mock_and_real_are_separate_and_error_fallback_is_not_cached(): void
    {
        config()->set('ai.fallback_to_mock', true);
        config()->set('ai.providers.openai.api_key', null);
        Http::fakeSequence()->push(['error' => ['message' => 'fake-secret']], 503)
            ->push($this->response());
        $mock = $this->service->embed('Text', $this->context());
        $this->assertTrue($mock['mock']);
        $this->assertTrue($this->service->embed('Text', $this->context())['cache_hit']);
        Http::assertNothingSent();

        config()->set('ai.providers.openai.api_key', 'fake-server-secret');
        $fallback = $this->service->embed('Text', $this->context());
        $this->assertTrue($fallback['mock']);
        $this->assertFalse($fallback['cache_hit']);
        $real = $this->service->embed('Text', $this->context());
        $this->assertFalse($real['mock']);
        $this->assertFalse($real['cache_hit']);
        $this->assertTrue($this->service->embed('Text', $this->context())['cache_hit']);
        Http::assertSentCount(2);
    }

    public function test_http_exception_releases_lock_and_never_caches_failure(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new ConnectionException('Do not leak fake-server-secret');
            }

            return Http::response($this->response());
        });
        try {
            $this->service->embed('Text', $this->context());
            $this->fail('Expected provider exception');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('fake-server-secret', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $store = $this->arrayStore();
        $this->assertEmpty($store->locks, 'Provider exception must release the cache lock');
        $this->assertEmpty($store->all(), 'Failed embedding must not be cached');
        $result = $this->service->embed('Text', $this->context());
        $this->assertFalse($result['cache_hit']);
        $this->assertSame(2, $attempts);
        $this->assertEmpty($store->locks);
        $this->assertSame(1, AiInteraksi::where('status', 'sukses')->count());
    }

    public function test_invalid_dimension_zero_vector_and_http_errors_are_not_cached(): void
    {
        Http::fakeSequence()->push($this->response([1, 0]))
            ->push($this->response([0, 0, 0]))
            ->push(['error' => ['message' => 'fake-server-secret']], 429)
            ->push($this->response());
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->service->embed('Text', $this->context());
                $this->fail('Expected invalid response exception');
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('fake-server-secret', $e->getMessage());
            }
            $this->assertEmpty(Cache::store()->getStore()->locks);
        }
        $this->assertFalse($this->service->embed('Text', $this->context())['cache_hit']);
        Http::assertSentCount(4);
    }

    private function useLockStore(ArrayStore $store): void
    {
        Cache::extend('embedding-lock-test', fn() => new Repository($store));
        config()->set('cache.stores.embedding-lock-test', ['driver' => 'embedding-lock-test']);
        config()->set('cache.default', 'embedding-lock-test');
    }

    public function test_waiting_request_rechecks_cache_after_acquiring_lock(): void
    {
        Http::fake();
        /** @var ArrayStore&\Mockery\MockInterface $store */
        $store = Mockery::mock(ArrayStore::class)->makePartial();
        $store->shouldReceive('lock')->once()->andReturnUsing(function ($key, $ttl) use ($store) {
            $this->assertGreaterThan(60, $ttl);
            $lock = Mockery::mock(Lock::class);
            $lock->shouldReceive('block')->once()->with(65)->andReturnUsing(function () use ($store, $key) {
                // Simulate the first worker finishing while this worker waits.
                $store->put(substr($key, 0, -5), [
                    'embedding' => [1, 0, 0],
                    'provider' => 'openai',
                    'model' => 'embedding-test',
                    'mock' => false,
                    'tokens' => 100,
                    'biaya' => 0.000002,
                    'cache_hit' => false,
                ], 86400);

                return true;
            });
            $lock->shouldReceive('release')->once()->andReturnTrue();

            return $lock;
        });
        $this->useLockStore($store);
        $result = $this->service->embed('Text', $this->context());
        $this->assertTrue($result['cache_hit']);
        $this->assertSame(0, $result['tokens']);
        $this->assertSame(0.0, $result['biaya']);
        Http::assertNothingSent();
        $this->assertSame(0, AiInteraksi::count());
    }

    public function test_lock_timeout_does_not_launch_duplicate_paid_request(): void
    {
        Http::fake();
        /** @var ArrayStore&\Mockery\MockInterface $store */
        $store = Mockery::mock(ArrayStore::class)->makePartial();
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException());
        $lock->shouldNotReceive('release'); // It belongs to another worker.
        $store->shouldReceive('lock')->once()->andReturn($lock);
        $this->useLockStore($store);
        try {
            $this->service->embed('Text', $this->context());
            $this->fail('Expected lock timeout');
        } catch (RuntimeException $e) {
            $this->assertSame('Embedding identik masih diproses. Coba lagi.', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_embedding_ttl_expiry_and_disabled_cache(): void
    {
        $this->fakeVectors();
        config()->set('ai.embedding_cache_ttl', 2);
        $this->service->embed('Text', $this->context());
        $this->travel(3)->seconds();
        $this->assertFalse($this->service->embed('Text', $this->context())['cache_hit']);
        config()->set('ai.embedding_cache_ttl', 0);
        $this->service->embed('Text', $this->context());
        $this->service->embed('Text', $this->context());
        Http::assertSentCount(4);
    }

    public function test_chunk_identity_reuses_persisted_vector_and_changes_or_force_reembed(): void
    {
        $this->fakeVectors();
        $chunk = $this->chunk();
        $this->service->embedChunk($chunk);
        $identity = $chunk->fresh()->embedding_identity;
        $this->assertSame(hash('sha256', 'Passage'), $identity['text_hash']);
        $this->assertSame(3, $identity['dimensions']);
        $this->assertSame('passage', $identity['input_type']);
        $this->assertFalse($identity['mock']);
        $this->assertSame(64, strlen($identity['signature']));
        $this->assertStringNotContainsString('fake-server-secret', json_encode($identity));
        Cache::flush(); // Persistent reuse must survive losing the cache backend.
        $this->service->embedChunk($chunk->fresh());
        Http::assertSentCount(1);
        $chunk->update(['teks' => 'Changed passage']);
        $this->service->embedChunk($chunk);
        config()->set('ai.embedding.model', 'new-model');
        $this->service->embedChunk($chunk);
        config()->set('ai.embedding.dimensions', 4);
        $this->service->embedChunk($chunk);
        $this->service->embedChunk($chunk, ['force' => true]);
        $this->service->embedChunk($chunk, ['no_cache' => true]);
        $this->service->embedChunk($chunk, force: true);
        Http::assertSentCount(7);
        $this->assertSame('new-model', $chunk->embedding_identity['model']);
        $this->assertSame(4, count($chunk->embedding));
    }

    public function test_zero_candidates_empty_allowlist_and_legacy_rows_do_not_embed_query(): void
    {
        Http::fake();
        $this->assertSame([], $this->search());
        $legacy = $this->chunk();
        $legacy->update(['embedding' => [1, 0, 0]]);
        $this->assertSame([], $this->search());
        $this->assertSame([], $this->search(['dokumen_ids' => []]));
        Http::assertNothingSent();
        $this->assertNull($legacy->fresh()->embedding_identity);
    }

    public function test_incompatible_identity_and_stale_text_are_excluded_before_query_embedding(): void
    {
        $this->fakeVectors();
        $chunk = $this->service->embedChunk($this->chunk());
        $original = $chunk->embedding_identity;
        foreach (
            [
                'model' => 'wrong',
                'provider' => 'nvidia',
                'dimensions' => 4,
                'mock' => true,
                'input_type' => 'query',
                'endpoint_hash' => 'wrong',
                'signature' => 'wrong'
            ] as $key => $value
        ) {
            $chunk->update(['embedding_identity' => array_replace($original, [$key => $value])]);
            $this->assertSame([], $this->search(), $key);
        }
        $chunk->update(['embedding_identity' => $original, 'embedding' => [1, 0]]);
        $this->assertSame([], $this->search());
        $chunk->update(['embedding' => [1, 0, 0], 'teks' => 'Changed without reindex']);
        $this->assertSame([], $this->search());
        Http::assertSentCount(1); // Only the original passage was embedded.
    }

    public function test_effective_model_change_excludes_old_vectors_without_paid_query(): void
    {
        $this->fakeVectors();
        $this->service->embedChunk($this->chunk());
        config()->set('ai.embedding.model', 'new-model');
        $this->assertSame([], $this->search());
        Http::assertSentCount(1);
    }

    public function test_retrieval_caches_only_ids_scores_and_rehydrates_current_scoped_models(): void
    {
        $this->fakeVectors();
        $chunk = $this->service->embedChunk($this->chunk());
        $first = $this->search();
        $second = $this->search();
        $this->assertSame($chunk->id, $second[0]['chunk']->id);
        $this->assertNotSame($first[0]['chunk'], $second[0]['chunk']);
        Http::assertSentCount(2);
        $entries = $this->retrievalEntries();
        $this->assertCount(1, $entries);
        $row = array_values($entries)[0]['value'][0];
        $this->assertSame(['id', 'score'], array_keys($row));
        $this->assertSame($chunk->id, $row['id']);
    }

    public function test_same_timestamp_vector_and_text_edits_invalidate_retrieval(): void
    {
        $this->fakeVectors();
        $chunk = $this->service->embedChunk($this->chunk());
        $this->assertEqualsWithDelta(1, $this->search()[0]['score'], 0.000001);
        $timestamp = $chunk->getRawOriginal('updated_at');
        DB::table('dokumen_chunk')->where('id', $chunk->id)->update(['embedding' => json_encode([0, 1, 0])]);
        $this->assertSame($timestamp, $chunk->fresh()->getRawOriginal('updated_at'));
        $this->assertEqualsWithDelta(0, $this->search()[0]['score'], 0.000001);
        DB::table('dokumen_chunk')->where('id', $chunk->id)->update(['teks' => 'Unindexed edit']);
        $this->assertSame([], $this->search());
        Http::assertSentCount(2); // Vector re-scoring reuses the query embedding.
        $this->service->embedChunk($chunk->fresh());
        $this->assertSame('Unindexed edit', $this->search()[0]['chunk']->teks);
        Http::assertSentCount(3);
    }

    public function test_document_source_tenant_and_deletion_changes_invalidate_cached_hits(): void
    {
        $this->fakeVectors();
        $chunk = $this->service->embedChunk($this->chunk());
        $document = $chunk->dokumen;
        $this->assertCount(1, $this->search(['sumber_konten' => true]));
        DB::table('dokumen_rujukan')->where('id', $document->id)->update(['sumber_konten' => false]);
        $this->assertSame([], $this->search(['sumber_konten' => true]));
        $this->assertCount(1, $this->search());
        $document->update(['judul' => 'Current title']);
        $this->assertSame('Current title', $this->search()[0]['chunk']->dokumen->judul);
        $other = Institusi::create(['nama' => 'Other']);
        $document->update(['institusi_id' => $other->id]);
        $this->assertSame([], $this->search());
        $document->update(['institusi_id' => $this->tenant->id]);
        $this->assertCount(1, $this->search());
        $document->delete();
        $this->assertSame([], $this->search());
        Http::assertSentCount(2);
    }

    public function test_filter_keys_sort_allowlists_and_respect_current_linked_document_ids(): void
    {
        $this->fakeVectors();
        $a = $this->service->embedChunk($this->chunk('A'));
        $b = $this->service->embedChunk($this->chunk('B'));
        $other = Institusi::create(['nama' => 'Other']);
        $foreign = $this->service->embedChunk($this->chunk('Foreign', $other));
        $this->assertCount(2, $this->search(['dokumen_ids' => [$b->dokumen_id, $a->dokumen_id]]));
        $this->assertCount(2, $this->search(['dokumen_ids' => [$a->dokumen_id, $b->dokumen_id, $a->dokumen_id]]));
        $this->assertCount(1, $this->retrievalEntries(), 'Sorted/deduplicated filters share one key');
        $this->assertCount(1, $this->search([], 1));
        $this->assertSame([], $this->search(['min_score' => 1.1]));
        $this->assertSame($b->id, $this->search(['dokumen_id' => $b->dokumen_id])[0]['chunk']->id);
        $this->assertSame([], $this->search(['dokumen_ids' => [$foreign->dokumen_id]]));
        $this->assertSame([], $this->search(['dokumen_ids' => []]));

        $link = MkDokumenRujukan::create([
            'institusi_id' => $this->tenant->id,
            'kode_mk' => 'TEST',
            'dokumen_rujukan_id' => $a->dokumen_id,
        ]);
        $linkedIds = fn() => MkDokumenRujukan::where('institusi_id', $this->tenant->id)
            ->where('kode_mk', 'TEST')->pluck('dokumen_rujukan_id')->all();
        $this->assertSame($a->id, $this->search(['dokumen_ids' => $linkedIds()])[0]['chunk']->id);
        $link->update(['dokumen_rujukan_id' => $b->dokumen_id]);
        $this->assertSame($b->id, $this->search(['dokumen_ids' => $linkedIds()])[0]['chunk']->id);
        $link->delete();
        $this->assertSame([], $this->search(['dokumen_ids' => $linkedIds()]));
        Http::assertSentCount(4); // Three passages + one shared tenant query.
    }

    public function test_new_chunks_invalidate_cached_empty_results_and_chunk_deletion_invalidates_hits(): void
    {
        $this->fakeVectors();
        $a = $this->service->embedChunk($this->chunk('A'));
        $a->update(['embedding' => [-1, 0, 0]]);
        $this->assertSame([], $this->search(['min_score' => 0.5]));
        $b = $this->service->embedChunk($this->chunk('B'));
        $this->assertSame($b->id, $this->search(['min_score' => 0.5])[0]['chunk']->id);
        $b->delete();
        $this->assertSame([], $this->search(['min_score' => 0.5]));
        Http::assertSentCount(3);
    }

    public function test_failed_real_query_does_not_score_or_cache_mock_fallback(): void
    {
        config()->set('ai.fallback_to_mock', true);
        Http::fakeSequence()->push($this->response())
            ->push(['error' => ['message' => 'failure']], 503)->push($this->response());
        $this->service->embedChunk($this->chunk());
        $this->assertSame([], $this->search());
        $this->assertEmpty($this->retrievalEntries());
        $this->assertCount(1, $this->search());
        Http::assertSentCount(3);
    }

    public function test_cosine_rejects_incompatible_vector_lengths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->cosine([1, 0, 0], [1, 0]);
    }

    public function test_legacy_chunk_reindex_records_identity_instead_of_trusting_old_vector(): void
    {
        $this->fakeVectors();
        $legacy = $this->chunk();
        $legacy->update(['embedding' => [0, 1, 0]]);
        $this->service->embedChunk($legacy);
        $this->assertSame([1, 0, 0], $legacy->fresh()->embedding);
        $this->assertSame('embedding-test', $legacy->embedding_identity['model']);
        $this->assertCount(1, $this->search());
        Http::assertSentCount(2);
    }

    public function test_fallback_chunk_records_mock_identity_and_is_reembedded_when_provider_recovers(): void
    {
        config()->set('ai.fallback_to_mock', true);
        Http::fakeSequence()->pushStatus(503)->push($this->response());
        $chunk = $this->service->embedChunk($this->chunk());
        $this->assertTrue($chunk->embedding_identity['mock']);
        $this->assertSame('mock', $chunk->embedding_identity['provider']);
        $this->assertSame([], $this->search());
        Http::assertSentCount(1);
        $this->service->embedChunk($chunk);
        $this->assertFalse($chunk->embedding_identity['mock']);
        Http::assertSentCount(2);
    }

    public function test_explicit_mock_provider_searches_only_matching_mock_chunks_without_http(): void
    {
        Http::fake();
        config()->set('ai.embedding.provider', 'mock');
        $chunk = $this->service->embedChunk($this->chunk());
        $this->assertTrue($chunk->embedding_identity['mock']);
        $this->assertSame($chunk->id, $this->search(['min_score' => -1])[0]['chunk']->id);
        $this->service->embedChunk($chunk);
        Http::assertNothingSent();
        $this->assertSame(2, AiInteraksi::count()); // Passage + query, no reuse log.
    }

    public function test_retrieval_ttl_expires_independently_of_query_embedding_ttl(): void
    {
        $this->fakeVectors();
        config()->set('ai.embedding_retrieval_cache_ttl', 2);
        $this->service->embedChunk($this->chunk());
        $this->search();
        $before = array_values($this->retrievalEntries())[0]['expiresAt'];
        $this->travel(3)->seconds();
        $this->search();
        $after = array_values($this->retrievalEntries())[0]['expiresAt'];
        $this->assertGreaterThan($before, $after);
        Http::assertSentCount(2);
        config()->set('ai.embedding_retrieval_cache_ttl', 0);
        Cache::flush();
        $this->search();
        $this->search();
        $this->assertEmpty($this->retrievalEntries());
        Http::assertSentCount(3);
    }

    public function test_authorization_is_rechecked_after_slow_query_embedding(): void
    {
        $other = Institusi::create(['nama' => 'Other']);
        $chunk = $this->chunk();
        $calls = 0;
        Http::fake(function () use (&$calls, $chunk, $other) {
            if (++$calls === 2) {
                // The source moved tenants while the query request was in flight.
                $chunk->dokumen->update(['institusi_id' => $other->id]);
            }

            return Http::response($this->response());
        });
        $this->service->embedChunk($chunk);
        $this->assertSame([], $this->search());
        $this->assertSame([], $this->search());
        Http::assertSentCount(2);
    }
}
