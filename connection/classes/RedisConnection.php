<?php

class RedisConnection
{
    /** @var \Redis|null */
    protected $redis;

    public function __construct()
    {
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

    /** @return \Redis|null */
    public function getRedis()
    {
        return $this->redis;
    }
}
