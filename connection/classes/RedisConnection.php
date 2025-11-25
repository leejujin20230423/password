<?php
//여기 오류나고 있음"
class RedisConnection
{
    /** @var \Redis|null */
    protected $redis;

    public function __construct()
    {
        // Redis 설정 로드
        $config = require __DIR__ . '/../config/redis.php';

        try {
            // 전역 네임스페이스의 Redis 클래스 사용
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port']);

            // 비밀번호가 설정돼 있을 때만 auth
            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }

            // ping 이 실패하면 그대로 중단
            if (!$redis->ping()) {
                die('Redis 연결 실패');
            }

            // 연결 성공 시에만 프로퍼티에 보관
            $this->redis = $redis;

        } catch (\Throwable $e) {   // \Exception 대신 \Throwable 로 넓게
            die('Redis 접속 실패: ' . $e->getMessage());
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
