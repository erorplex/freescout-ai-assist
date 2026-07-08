<?php

namespace Modules\AiAssist\Tests\Support;

use Modules\AiAssist\Services\Llm\HttpClient;

class FakeHttpClient implements HttpClient
{
    public array $lastRequest = [];
    public int $calls = 0;
    /** @var array<int,array> queued responses */
    public array $queue;

    public function __construct(array $responses = [])
    {
        $this->queue = $responses;
    }

    public function request(string $method, string $url, array $headers, ?array $body, int $timeout): array
    {
        $this->calls++;
        $this->lastRequest = compact('method', 'url', 'headers', 'body', 'timeout');
        return array_shift($this->queue) ?? ['status' => 200, 'json' => [], 'transport_error' => null];
    }
}
