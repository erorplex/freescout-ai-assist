<?php

namespace Modules\AiAssist\Services\Cache;

class ArrayCache implements CacheInterface
{
    private array $store = [];

    public function get(string $key)
    {
        return $this->store[$key] ?? null;
    }

    public function put(string $key, $value, int $ttlSeconds): void
    {
        $this->store[$key] = $value;
    }
}
