<?php

namespace Modules\AiAssist\Services\Cache;

class FreescoutCache implements CacheInterface
{
    public function get(string $key)
    {
        return \Cache::get($key);
    }

    public function put(string $key, $value, int $ttlSeconds): void
    {
        \Cache::put($key, $value, now()->addSeconds($ttlSeconds));
    }
}
