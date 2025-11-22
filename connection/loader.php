<?php

require_once __DIR__ . '/../vendor/autoload.php';

// ENV 로드
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/env');
$dotenv->load();

// 설정 파일 로드
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/redis.php';

// 클래스 로드
require_once __DIR__ . '/classes/DBConnection.php';
require_once __DIR__ . '/classes/RedisConnection.php';
