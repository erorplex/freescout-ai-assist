<?php

namespace Modules\AiAssist\Tests;

use Modules\AiAssist\Services\Llm\ClaudeProvider;
use Modules\AiAssist\Services\Llm\OpenAiProvider;
use Modules\AiAssist\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

class ProviderRequestShapingTest extends TestCase
{
    public function testClaudeBodyShapeAndTextParse(): void
    {
        $http = new FakeHttpClient([[
            'status' => 200,
            'json'   => ['content' => [['type' => 'text', 'text' => 'Guten Tag']], 'stop_reason' => 'end_turn'],
            'transport_error' => null,
        ]]);
        $p = new ClaudeProvider('sk-key', 'claude-haiku-4-5', $http);
        $out = $p->draft([['role' => 'user', 'content' => 'hi']], 'SYS', []);

        $this->assertSame('Guten Tag', $out);
        $body = $http->lastRequest['body'];
        $this->assertSame('claude-haiku-4-5', $body['model']);
        $this->assertSame('SYS', $body['system']);            // system is TOP-LEVEL for Claude
        $this->assertSame(1024, $body['max_tokens']);
        $this->assertSame(0.3, $body['temperature']);
        $this->assertStringContainsString('https://api.anthropic.com/v1/messages', $http->lastRequest['url']);
        $this->assertContains('anthropic-version: 2023-06-01', $http->lastRequest['headers']);
    }

    public function testClaudeRefusalOrEmptyYieldsEmptyDraft(): void
    {
        $http = new FakeHttpClient([[
            'status' => 200,
            'json'   => ['content' => [['type' => 'text', 'text' => 'x']], 'stop_reason' => 'refusal'],
            'transport_error' => null,
        ]]);
        $p = new ClaudeProvider('sk', 'm', $http);
        $this->assertSame('', $p->draft([['role' => 'user', 'content' => 'hi']], 'SYS', []));
    }

    public function testOpenAiFoldsSystemIntoMessagesZeroAndSendsStoreFalse(): void
    {
        $http = new FakeHttpClient([[
            'status' => 200,
            'json'   => ['choices' => [['message' => ['content' => 'Antwort']]]],
            'transport_error' => null,
        ]]);
        $p = new OpenAiProvider('sk-openai', 'gpt-4o-mini', $http);
        $out = $p->draft([['role' => 'user', 'content' => 'hi']], 'SYS', []);

        $this->assertSame('Antwort', $out);
        $body = $http->lastRequest['body'];
        $this->assertSame('system', $body['messages'][0]['role']);
        $this->assertSame('SYS', $body['messages'][0]['content']);
        $this->assertSame('user', $body['messages'][1]['role']);
        $this->assertFalse($body['store']);
        // GPT-5 / o-series reject the legacy max_tokens with HTTP 400 — the
        // OpenAI request MUST use max_completion_tokens and never max_tokens.
        $this->assertArrayHasKey('max_completion_tokens', $body);
        $this->assertArrayNotHasKey('max_tokens', $body);
        $this->assertSame(1024, $body['max_completion_tokens']);
        $this->assertStringContainsString('https://api.openai.com/v1/chat/completions', $http->lastRequest['url']);
    }

    public function testProviderThrowsOnNon2xx(): void
    {
        $http = new FakeHttpClient([[
            'status' => 401,
            'json'   => ['error' => ['message' => 'invalid x-api-key']],
            'transport_error' => null,
        ]]);
        $p = new ClaudeProvider('sk', 'm', $http);
        $this->expectException(\RuntimeException::class);
        $p->draft([['role' => 'user', 'content' => 'hi']], 'SYS', []);
    }
}
