<?php

namespace Modules\AiAssist\Services\Cache;

interface CacheInterface
{
    /** @return mixed|null null when missing */
    public function get(string $key);

    public function put(string $key, $value, int $ttlSeconds): void;
}
