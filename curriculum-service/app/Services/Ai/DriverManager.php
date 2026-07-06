<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\Driver;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\MockDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use InvalidArgumentException;

class DriverManager
{
    public function make(string $driver): Driver
    {
        return match ($driver) {
            'anthropic' => new AnthropicDriver(),
            'openai'    => new OpenAiDriver(),
            'mock'      => new MockDriver(),
            default     => throw new InvalidArgumentException("Driver AI tidak dikenal: {$driver}"),
        };
    }
}
