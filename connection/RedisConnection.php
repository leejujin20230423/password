<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/EnvBox.php';

use PassApp\Core\EnvBox;

class RedisConnection
{
    private $redis = null;

    public function __construct()
    {
        EnvBox::boot(dirname(__DIR__) . '/.env');

        // redis 확장(phpredis)이 있으면 연결 시도
        if (class_exists('Redis')) {
            $host = EnvBox::get('REDIS_HOST', '127.0.0.1');
            $port = (int)EnvBox::get('REDIS_PORT', '6379');
            $timeout = (float)EnvBox::get('REDIS_TIMEOUT', '1.5');

            $r = new Redis();
            // 연결 실패해도 fatal 안 나게 처리
            try {
                if (@$r->connect($host, $port, $timeout)) {
                    // 비번이 있으면 auth
                    $redisPass = EnvBox::get('REDIS_PASSWORD', EnvBox::get('REDIS_PASS', ''));
                    if ($redisPass !== '') {
                        @$r->auth($redisPass);
                    }
                    // DB index 선택
                    $redisDb = EnvBox::get('REDIS_DB', '');
                    if ($redisDb !== '') {
                        @$r->select((int)$redisDb);
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
