<?php

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// 나머지 기존 코드들


$envDir = __DIR__ . '/env';

$dotenv = Dotenv\Dotenv::createImmutable($envDir);
$dotenv->load();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/redis.php';

require_once __DIR__ . '/classes/DBConnection.php';
require_once __DIR__ . '/classes/RedisConnection.php';
