<?php

class RedisConnection
{
    private $redis = null;

    public function __construct()
    {
        // redis 확장(phpredis)이 있으면 연결 시도
        if (class_exists('Redis')) {
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $timeout = (float)($_ENV['REDIS_TIMEOUT'] ?? 1.5);

            $r = new Redis();
            // 연결 실패해도 fatal 안 나게 처리
            try {
                if (@$r->connect($host, $port, $timeout)) {
                    // 비번이 있으면 auth
                    if (!empty($_ENV['REDIS_PASS'])) {
                        @$r->auth((string)$_ENV['REDIS_PASS']);
                    }
                    // DB index 선택
                    if (isset($_ENV['REDIS_DB'])) {
                        @$r->select((int)$_ENV['REDIS_DB']);
                    }
                    $this->redis = $r;
                }
            } catch (Throwable $e) {
                $this->redis = null;
            }
        }
    }

    public function getRedis()
    {
        // Redis가 없어도 프로젝트가 계속 진행되게 null 반환
        return $this->redis;
    }
}
