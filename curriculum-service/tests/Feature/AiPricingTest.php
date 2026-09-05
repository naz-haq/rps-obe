<?php

namespace Tests\Feature;

use App\Models\AiKredensial;
use App\Models\Institusi;
use App\Services\Ai\AiService;
use App\Services\Ai\Exceptions\AiBudgetException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Proteksi akuntansi biaya (§4.3/§4.4): harga model live, klasifikasi
 * billing_status, dan pengenalan provider gratis. Tidak menyentuh DB.
 */
class AiPricingTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): AiService
    {
        return app(AiService::class);
    }

    public function test_provider_gratis_dikenali(): void
    {
        $s = $this->svc();
        $this->assertTrue($s->isFreeProvider('nvidia'));
        $this->assertTrue($s->isFreeProvider('mock'));
        $this->assertTrue($s->isFreeProvider('github'));
        $this->assertFalse($s->isFreeProvider('openai'));
        $this->assertFalse($s->isFreeProvider('anthropic'));
    }

    public function test_harga_diketahui_untuk_katalog_dan_live_gratis(): void
    {
        $s = $this->svc();
        $this->assertTrue($s->priceKnown('gpt-5-4'), 'model katalog berbayar');
        $this->assertTrue($s->priceKnown('nvidia::nvidia/nemotron-3-super-120b-a12b'), 'live provider gratis');
        $this->assertTrue($s->priceKnown('openai::gpt-5.6-luna'), 'live berbayar tapi ada di katalog harga');
    }

    public function test_harga_tak_dikenal_untuk_live_berbayar_tanpa_katalog(): void
    {
        $s = $this->svc();
        $this->assertFalse($s->priceKnown('openai::model-belum-terdaftar-xyz'));
        $this->assertFalse($s->priceKnown('gemini::gemini-x-pro-unlisted'));
    }

    public function test_model_tak_dikenal_harga_tak_diketahui(): void
    {
        $this->assertFalse($this->svc()->priceKnown('model-yang-tidak-ada'));
    }

    public function test_klasifikasi_billing_status(): void
    {
        $s = $this->svc();
        $m = new ReflectionMethod(AiService::class, 'classifyBilling'); // privat; PHP 8.1+ tak butuh setAccessible

        $this->assertSame('cache', $m->invoke($s, true, 'openai', true));
        $this->assertSame('mock', $m->invoke($s, false, 'mock', true));
        $this->assertSame('free', $m->invoke($s, false, 'nvidia', true));
        $this->assertSame('known', $m->invoke($s, false, 'openai', true));
        $this->assertSame('unknown', $m->invoke($s, false, 'openai', false));
    }

    public function test_kunci_cache_terisolasi_per_tenant_dan_versi(): void
    {
        $method = new ReflectionMethod(AiService::class, 'promptCacheKey');
        $credentials = ['provider' => 'openai', 'model_array' => ['model' => 'gpt-test']];
        $params = ['temperature' => 0.2, 'max_tokens' => 100];

        $tenantOne = $method->invoke($this->svc(), 'generate', $credentials, 'system', 'prompt', $params, [
            'institusi_id' => 1,
            'prompt_version' => 'v1',
            'schema_version' => 'v1',
            'source_version' => 'v1',
        ]);
        $tenantTwo = $method->invoke($this->svc(), 'generate', $credentials, 'system', 'prompt', $params, [
            'institusi_id' => 2,
            'prompt_version' => 'v1',
            'schema_version' => 'v1',
            'source_version' => 'v1',
        ]);
        $newSchema = $method->invoke($this->svc(), 'generate', $credentials, 'system', 'prompt', $params, [
            'institusi_id' => 1,
            'prompt_version' => 'v1',
            'schema_version' => 'v2',
            'source_version' => 'v1',
        ]);

        $this->assertNotSame($tenantOne, $tenantTwo);
        $this->assertNotSame($tenantOne, $newSchema);
    }

    public function test_live_paid_model_without_catalog_price_is_blocked_before_provider_call(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Harga model');

        $this->svc()->run('generate', 'system', 'prompt', [
            'model' => 'openai::model-belum-terdaftar-xyz',
        ]);
    }

    public function test_active_budget_reservations_prevent_concurrent_overspend(): void
    {
        $institution = Institusi::create(['kode' => 'BUDGET', 'nama' => 'Budget', 'jenis' => 'prodi']);
        $credential = AiKredensial::create([
            'institusi_id' => $institution->id,
            'provider' => 'openai',
            'api_key_encrypted' => 'test-key',
            'anggaran' => 1,
            'aktif' => true,
        ]);
        $method = new ReflectionMethod(AiService::class, 'reserveBudget');

        $reservationId = $method->invoke($this->svc(), $credential, $institution->id, 0.6);
        $this->assertIsInt($reservationId);

        $this->expectException(AiBudgetException::class);
        $method->invoke($this->svc(), $credential, $institution->id, 0.6);
    }
}
