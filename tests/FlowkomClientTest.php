<?php

namespace Modules\AiAssist\Tests;

use Modules\AiAssist\Services\Cache\ArrayCache;
use Modules\AiAssist\Services\FlowkomClient;
use Modules\AiAssist\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

class FlowkomClientTest extends TestCase
{
    public function testCapabilityPositiveIsCached(): void
    {
        $http = new FakeHttpClient([
            ['status' => 200, 'json' => ['version' => 1, 'ai_draft' => true], 'transport_error' => null],
        ]);
        $c = new FlowkomClient('https://app.flowkom.de', 'key', $http, new ArrayCache());
        $this->assertTrue($c->available());
        $this->assertTrue($c->available()); // served from cache
        $this->assertSame(1, $http->calls);
    }

    public function testCapabilityFailClosedOnNon2xx(): void
    {
        $http = new FakeHttpClient([
            ['status' => 500, 'json' => null, 'transport_error' => null],
        ]);
        $c = new FlowkomClient('https://app.flowkom.de', 'key', $http, new ArrayCache());
        $this->assertFalse($c->available());
    }

    public function testCapabilityFailClosedWhenAiDraftMissingOrFalse(): void
    {
        $http = new FakeHttpClient([
            ['status' => 200, 'json' => ['version' => 1, 'ai_draft' => false], 'transport_error' => null],
        ]);
        $c = new FlowkomClient('https://app.flowkom.de', 'key', $http, new ArrayCache());
        $this->assertFalse($c->available());
    }

    public function testDraftSendsOnlyVersionTicketGuidanceKb(): void
    {
        $http = new FakeHttpClient([
            ['status' => 200, 'json' => ['version' => 1, 'draft' => 'Guten Tag'], 'transport_error' => null],
        ]);
        $c = new FlowkomClient('https://app.flowkom.de', 'integration-key', $http, new ArrayCache());
        $payload = [
            'version'  => 1,
            'ticket'   => ['subject' => 's', 'channel' => 'ebay', 'messages' => []],
            'guidance' => ['version' => 1, 'global' => [], 'channels' => []],
            'kb'       => ['text' => 'x'],
        ];
        $res = $c->draft($payload);
        $this->assertSame('Guten Tag', $res['draft']);
        $sent = $http->lastRequest['body'];
        $this->assertSame(['version', 'ticket', 'guidance', 'kb'], array_keys($sent)); // NOTHING else — no key, no notes
        $this->assertStringContainsString('/api/freescout/ai-draft', $http->lastRequest['url']);
        $this->assertContains('Authorization: Bearer integration-key', $http->lastRequest['headers']);
    }

    public function testDraftThrowsMappedMessageOnErrorStatus(): void
    {
        $http = new FakeHttpClient([
            ['status' => 401, 'json' => ['version' => 1, 'code' => 'unauthenticated'], 'transport_error' => null],
        ]);
        $c = new FlowkomClient('https://app.flowkom.de', 'key', $http, new ArrayCache());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API-Key ungueltig');
        $c->draft(['version' => 1, 'ticket' => [], 'guidance' => [], 'kb' => null]);
    }

    public function testErrorCodeMapping(): void
    {
        $this->assertStringContainsString('API-Key', FlowkomClient::mapErrorCode(401, 'unauthenticated'));
        $this->assertStringContainsString('Rate-Limit', FlowkomClient::mapErrorCode(429, 'rate_limited'));
        $this->assertStringContainsString('nicht erreichbar', FlowkomClient::mapErrorCode(502, 'provider_error'));
        $this->assertStringContainsString('deaktiviert', FlowkomClient::mapErrorCode(403, 'ai_draft_disabled'));
    }
}
