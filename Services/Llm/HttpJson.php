<?php

namespace Modules\AiAssist\Services\Llm;

/**
 * Native-curl JSON client. Zero dependencies. No streaming, no auto-retry
 * (manual re-click is the only retry in v0).
 */
class HttpJson implements HttpClient
{
    public function request(string $method, string $url, array $headers, ?array $body, int $timeout): array
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout ?: 20,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $options);

        $raw      = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            return ['status' => 0, 'json' => null, 'transport_error' => $curlErr];
        }

        $decoded = json_decode((string) $raw, true);
        return [
            'status'          => $status,
            'json'            => is_array($decoded) ? $decoded : null,
            'transport_error' => null,
        ];
    }
}
