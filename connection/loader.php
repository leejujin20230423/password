<?php

// 1. Composer autoload (vendor)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // 개발 중 디버그용 (원하면 지워도 됨)
    error_log("Composer autoload not found: " . $autoloadPath);
}

// 2. .env 파일 로드 (env 폴더 안의 .env)
if (class_exists(\Dotenv\Dotenv::class)) {
    // /var/www/pass/env/.env 을 기준으로 읽음
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../env');
    $dotenv->safeLoad();   // .env 없으면 조용히 넘어감
}

// 3. DB / Redis 설정 파일 로드
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/redis.php';

// 4. (있다면) DBConnection / RedisConnection 클래스 로드
// require_once __DIR__ . '/classes/DBConnection.php';
// require_once __DIR__ . '/classes/RedisConnection.php';
