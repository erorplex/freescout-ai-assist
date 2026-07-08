<?php

namespace Modules\AiAssist\Services\Llm;

class ClaudeProvider implements AiProvider
{
    const URL = 'https://api.anthropic.com/v1/messages';

    private string $apiKey;
    private string $model;
    private HttpClient $http;

    public function __construct(string $apiKey, string $model, HttpClient $http)
    {
        $this->apiKey = $apiKey;
        $this->model  = $model;
        $this->http   = $http;
    }

    public function draft(array $messages, string $system, array $opts): string
    {
        $body = [
            'model'       => $this->model,
            'max_tokens'  => (int) ($opts['max_tokens'] ?? 1024),
            'system'      => $system,
            'messages'    => $messages,
            'temperature' => $opts['temperature'] ?? 0.3,
        ];
        $headers = [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
            'accept: application/json',
        ];

        $res = $this->http->request('POST', self::URL, $headers, $body, 20);
        self::assertOk($res);

        $json = $res['json'] ?? [];
        if (($json['stop_reason'] ?? null) === 'refusal') {
            return '';
        }
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return trim((string) ($block['text'] ?? ''));
            }
        }
        return '';
    }

    private static function assertOk(array $res): void
    {
        if (($res['transport_error'] ?? null) !== null) {
            throw new \RuntimeException('KI-Anbieter nicht erreichbar: ' . $res['transport_error']);
        }
        $status = (int) ($res['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            $msg = $res['json']['error']['message'] ?? ('HTTP ' . $status);
            throw new \RuntimeException((string) $msg);
        }
    }
}
