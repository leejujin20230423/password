<?php

class RedisConnection
{
    /** @var \Redis|null */
    protected $redis;

    public function __construct()
    {
        // Redis 확장이 아예 없으면 그냥 null 로 두고 빠져나감
        if (!class_exists(\Redis::class)) {
            // error_log 로만 남기고, 프로그램은 계속 진행
            error_log('php-redis extension not loaded. RedisConnection will be null.');
            $this->redis = null;
            return;
        }

        $config = require __DIR__ . '/../config/redis.php';

        try {
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port']);

            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }

            if (!$redis->ping()) {
                error_log('Redis ping 실패');
                $this->redis = null;
                return;
            }

            $this->redis = $redis;

        } catch (\Throwable $e) {
            error_log('Redis 접속 실패: ' . $e->getMessage());
            $this->redis = null;
        }
    }

    /**
     * @return \Redis|null
     */
    public function getRedis()
    {
        return $this->redis;
    }
}
