<?php

namespace App\Services\Ai;

class CostCalculator
{
    /**
     * Hitung biaya USD dari token riil x harga (USD per 1 juta token).
     *
     * @param array $pricing ['input','output','cache_read','cache_write'] USD / 1M token
     */
    public function usd(array $pricing, LlmResult $r): float
    {
        $perM = 1_000_000;

        return ($r->inputTokens / $perM) * ($pricing['input'] ?? 0)
            + ($r->outputTokens / $perM) * ($pricing['output'] ?? 0)
            + ($r->cacheReadTokens / $perM) * ($pricing['cache_read'] ?? 0)
            + ($r->cacheWriteTokens / $perM) * ($pricing['cache_write'] ?? 0);
    }
}
