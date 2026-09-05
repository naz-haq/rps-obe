<?php

namespace Tests\Unit;

use App\Services\Ai\Drivers\OpenAiDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiDriverTest extends TestCase
{
    public function test_openai_gpt_5_uses_max_completion_tokens(): void
    {
        Http::fake(['*' => Http::response($this->successResponse(), 200)]);

        $this->driver()->run($this->model('openai', 'gpt-5.6-luna'), 'system', 'prompt', $this->params());

        Http::assertSent(
            fn(Request $request) =>
            $request['max_completion_tokens'] === 512
                && ! isset($request['max_tokens'])
                && ! isset($request['temperature'])
        );
    }

    public function test_openai_gpt_5_mini_also_drops_temperature(): void
    {
        Http::fake(['*' => Http::response($this->successResponse(), 200)]);

        $this->driver()->run($this->model('openai', 'gpt-5-mini'), 'system', 'prompt', $this->params());

        Http::assertSent(
            fn(Request $request) =>
            $request['max_completion_tokens'] === 512
                && ! isset($request['max_tokens'])
                && ! isset($request['temperature'])
        );
    }

    public function test_openai_o_series_also_uses_max_completion_tokens(): void
    {
        Http::fake(['*' => Http::response($this->successResponse(), 200)]);

        $this->driver()->run($this->model('openai', 'o4-mini'), 'system', 'prompt', $this->params());

        Http::assertSent(
            fn(Request $request) =>
            $request['max_completion_tokens'] === 512
                && ! isset($request['max_tokens'])
                && ! isset($request['temperature'])
        );
    }

    public function test_compatible_providers_keep_using_max_tokens(): void
    {
        Http::fake(['*' => Http::response($this->successResponse(), 200)]);

        $this->driver()->run($this->model('gemini', 'gemini-2.5-flash-lite'), 'system', 'prompt', $this->params());

        Http::assertSent(
            fn(Request $request) =>
            $request['max_tokens'] === 512
                && ! isset($request['max_completion_tokens'])
                && $request['temperature'] === 0.2
        );
    }

    private function driver(): OpenAiDriver
    {
        return new OpenAiDriver;
    }

    private function model(string $provider, string $model): array
    {
        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => 'test-key',
            'base_url' => 'https://example.test/v1',
        ];
    }

    private function params(): array
    {
        return ['temperature' => 0.2, 'max_tokens' => 512];
    }

    private function successResponse(): array
    {
        return [
            'model' => 'test-model',
            'choices' => [['message' => ['content' => 'OK'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1],
        ];
    }
}
