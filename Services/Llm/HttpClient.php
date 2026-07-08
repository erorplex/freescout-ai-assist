<?php

namespace Modules\AiAssist\Services\Llm;

/**
 * Minimal JSON-over-HTTP contract. Injected everywhere a network call
 * happens so the pure request/response shaping is unit-testable without curl.
 *
 * request() returns: ['status'=>int, 'json'=>?array, 'transport_error'=>?string]
 */
interface HttpClient
{
    public function request(string $method, string $url, array $headers, ?array $body, int $timeout): array;
}
