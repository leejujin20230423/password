<?php

class RedisConnection
{
    protected $redis;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/redis.php';

        try {
            $this->redis = new Redis();
            $this->redis->connect($config["host"], $config["port"]);

            if (!empty($config["password"])) {
                $this->redis->auth($config["password"]);
            }

            if (!$this->redis->ping()) {
                die("Redis 연결 실패");
            }
        } catch (Exception $e) {
            die("Redis 접속 실패: " . $e->getMessage());
        }
    }

    public function getRedis()
    {
        return $this->redis;
    }
}
