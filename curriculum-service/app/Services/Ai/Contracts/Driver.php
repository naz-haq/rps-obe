<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\LlmResult;

interface Driver
{
    /**
     * Jalankan prompt terhadap model.
     *
     * @param array  $model   ['api_key','base_url','model','pricing']
     * @param string $system  System prompt
     * @param string $prompt  User prompt
     * @param array  $params  ['temperature' => float, 'max_tokens' => int]
     */
    public function run(array $model, string $system, string $prompt, array $params): LlmResult;
}
