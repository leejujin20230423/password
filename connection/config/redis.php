<?php

// Redis 설정 파일
// .env 값이 없거나 로드가 안 된 경우를 대비해서 기본값도 같이 지정

return [
    'host'     => $_ENV['REDIS_HOST']     ?? getenv('REDIS_HOST')     ?: '127.0.0.1',
    'port'     => (int)($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT')   ?: 6379),
    'password' => $_ENV['REDIS_PASSWORD'] ?? getenv('REDIS_PASSWORD') ?: null,
];
