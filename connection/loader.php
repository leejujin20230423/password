<?php

// require_once __DIR__ . '/../vendor/autoload.php';

$envDir = __DIR__ . '/env';

$dotenv = Dotenv\Dotenv::createImmutable($envDir);
$dotenv->load();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/redis.php';

require_once __DIR__ . '/classes/DBConnection.php';
require_once __DIR__ . '/classes/RedisConnection.php';
