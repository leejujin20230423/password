<?php
// 이 파일은 "IDE / 인텔리센스용" 가짜 Redis 클래스 정의야.
// 실제로 phpredis 확장에서 class Redis 가 이미 있으면 실행 시에는 이 코드가 무시됨.

if (!class_exists('Redis')) {
    /**
     * @internal IDE helper stub for phpredis
     */
    class Redis
    {
        public function connect(string $host, int $port = 6379) {}
        public function auth(string $password) {}
        public function ping() {}
    }
}
