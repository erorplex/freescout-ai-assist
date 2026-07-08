<?php

namespace Modules\AiAssist\Services;

use Modules\AiAssist\Services\Cache\CacheInterface;
use Modules\AiAssist\Services\Llm\HttpClient;

/**
 * CONNECTED-mode client. Capability handshake mirrors Brainflow::available()
 * (fail-closed, cached 10 min positive / 1 min negative). The request body is
 * byte-identical to the PROJ-597 seam: exactly {version, ticket, guidance, kb}.
 */
class FlowkomClient
{
    const CAP_POSITIVE_SECONDS = 600; // 10 min
    const CAP_NEGATIVE_SECONDS = 60;  // 1 min

    private string $apiUrl;
    private string $apiKey;
    private HttpClient $http;
    private CacheInterface $cache;

    public function __construct(string $apiUrl, string $apiKey, HttpClient $http, CacheInterface $cache)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->http   = $http;
        $this->cache  = $cache;
    }

    public function available(): bool
    {
        if ($this->apiUrl === '' || $this->apiKey === '') {
            return false;
        }
        $cacheKey = 'aiassist.cap.' . md5($this->apiUrl);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $available = false;
        try {
            $res = $this->http->request('GET', $this->apiUrl . '/api/freescout/ai-draft', $this->headers(false), null, 10);
            if (($res['transport_error'] ?? null) === null) {
                $status = (int) ($res['status'] ?? 0);
                if ($status >= 200 && $status < 300) {
                    $available = !empty($res['json']['ai_draft']);
                }
            }
        } catch (\Throwable $e) {
            $available = false;
        }

        $this->cache->put($cacheKey, $available ? 1 : 0, $available ? self::CAP_POSITIVE_SECONDS : self::CAP_NEGATIVE_SECONDS);
        return $available;
    }

    /**
     * POST /ai-draft. $payload MUST be exactly {version, ticket, guidance, kb}.
     * @return array decoded {draft, grounding, meta}
     */
    public function draft(array $payload): array
    {
        $res = $this->http->request('POST', $this->apiUrl . '/api/freescout/ai-draft', $this->headers(true), $payload, 20);
        if (($res['transport_error'] ?? null) !== null) {
            throw new \RuntimeException(self::mapErrorCode(0, null));
        }
        $status = (int) ($res['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            $code = $res['json']['code'] ?? null;
            throw new \RuntimeException(self::mapErrorCode($status, is_string($code) ? $code : null));
        }
        return $res['json'] ?? [];
    }

    /**
     * Machine code + status -> honest German UI hint. Never surfaces
     * error.message / DB text.
     */
    public static function mapErrorCode(int $status, ?string $code): string
    {
        switch ($status) {
            case 400:
                return 'Anfrage ungueltig.';
            case 401:
                return 'API-Key ungueltig.';
            case 403:
                return 'KI-Datenzugriff ist in Flowkom deaktiviert.';
            case 422:
                return 'Kein KI-Anbieter konfiguriert oder nichts zu entwerfen.';
            case 429:
                return 'Rate-Limit erreicht — bitte spaeter erneut versuchen.';
        }
        if ($status >= 500) {
            return 'KI-Anbieter nicht erreichbar.';
        }
        return 'Flowkom nicht erreichbar.';
    }

    private function headers(bool $json): array
    {
        $h = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];
        if ($json) {
            $h[] = 'Content-Type: application/json';
        }
        return $h;
    }
}
