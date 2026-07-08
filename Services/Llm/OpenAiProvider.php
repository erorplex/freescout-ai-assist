<?php

namespace Modules\AiAssist\Services\Llm;

class OpenAiProvider implements AiProvider
{
    const URL = 'https://api.openai.com/v1/chat/completions';

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
        $withSystem = array_merge([['role' => 'system', 'content' => $system]], $messages);
        $body = [
            'model'       => $this->model,
            'messages'    => $withSystem,          // system folded into messages[0]
            'temperature' => $opts['temperature'] ?? 0.3,
            'max_tokens'  => (int) ($opts['max_tokens'] ?? 1024),
            'store'       => false,
        ];
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'content-type: application/json',
            'accept: application/json',
        ];

        $res = $this->http->request('POST', self::URL, $headers, $body, 20);
        if (($res['transport_error'] ?? null) !== null) {
            throw new \RuntimeException('KI-Anbieter nicht erreichbar: ' . $res['transport_error']);
        }
        $status = (int) ($res['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            $msg = $res['json']['error']['message'] ?? ('HTTP ' . $status);
            throw new \RuntimeException((string) $msg);
        }

        return trim((string) ($res['json']['choices'][0]['message']['content'] ?? ''));
    }
}
