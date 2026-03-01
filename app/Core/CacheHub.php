<?php
declare(strict_types=1);

namespace PassApp\Core;

use Redis;
use Throwable;

final class CacheHub
{
    private static ?Redis $redis = null;

    public static function warm(): void
    {
        // Optional: no Redis installed => keep null
        self::redis();
    }

    public static function redis(): ?Redis
    {
        if (self::$redis instanceof Redis) {
            return self::$redis;
        }

        if (!class_exists(Redis::class)) {
            return null;
        }

        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
        $timeout = (float)($_ENV['REDIS_TIMEOUT'] ?? 1.5);

        try {
            $r = new Redis();
            $r->connect($host, $port, $timeout);

            $auth = $_ENV['REDIS_PASSWORD'] ?? null;
            if (is_string($auth) && $auth !== '') {
                $r->auth($auth);
            }

            $dbIndex = (int)($_ENV['REDIS_DB'] ?? 0);
            if ($dbIndex > 0) {
                $r->select($dbIndex);
            }

            self::$redis = $r;
        } catch (Throwable) {
            self::$redis = null;
        }

        return self::$redis;
    }

    public static function forgetByPrefix(string $prefix): void
    {
        $r = self::redis();
        if (!$r) return;

        $it = null;
        while ($keys = $r->scan($it, $prefix . '*', 200)) {
            foreach ($keys as $k) {
                $r->del((string)$k);
            }
        }
    }
}
