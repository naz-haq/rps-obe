<?php

namespace Tests\Feature;

use App\Services\Ai\AiService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Proteksi akuntansi biaya (§4.3/§4.4): harga model live, klasifikasi
 * billing_status, dan pengenalan provider gratis. Tidak menyentuh DB.
 */
class AiPricingTest extends TestCase
{
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
}
