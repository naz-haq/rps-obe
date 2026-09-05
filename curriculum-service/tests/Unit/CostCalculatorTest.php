<?php

namespace Tests\Unit;

use App\Services\Ai\CostCalculator;
use App\Services\Ai\LlmResult;
use PHPUnit\Framework\TestCase;

class CostCalculatorTest extends TestCase
{
    private CostCalculator $cost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cost = new CostCalculator();
    }

    public function test_menghitung_biaya_input_dan_output(): void
    {
        // Harga USD / 1 juta token.
        $pricing = ['input' => 2.00, 'output' => 10.00];
        $result = new LlmResult(text: 'x', inputTokens: 1_000_000, outputTokens: 500_000);

        // 1.0M×2.00 + 0.5M×10.00 = 2.00 + 5.00 = 7.00
        $this->assertEqualsWithDelta(7.00, $this->cost->usd($pricing, $result), 1e-9);
    }

    public function test_baseline_satu_rps_mendekati_estimasi_audit(): void
    {
        // Skenario baseline audit (Luna): 13.269 input + 7.433 output token.
        $pricing = ['input' => 0.20, 'output' => 1.20];
        $result = new LlmResult(text: 'x', inputTokens: 13_269, outputTokens: 7_433);

        $this->assertEqualsWithDelta(0.0115734, $this->cost->usd($pricing, $result), 1e-6);
    }

    public function test_harga_nol_menghasilkan_biaya_nol(): void
    {
        $pricing = ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0];
        $result = new LlmResult(text: 'x', inputTokens: 5000, outputTokens: 4000);

        $this->assertSame(0.0, $this->cost->usd($pricing, $result));
    }

    public function test_menghitung_token_cache(): void
    {
        $pricing = ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.02, 'cache_write' => 0.10];
        $result = new LlmResult(text: 'x', cacheReadTokens: 1_000_000, cacheWriteTokens: 2_000_000);

        // 1M×0.02 + 2M×0.10 = 0.02 + 0.20 = 0.22
        $this->assertEqualsWithDelta(0.22, $this->cost->usd($pricing, $result), 1e-9);
    }
}
