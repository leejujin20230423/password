<?php

require_once __DIR__ . '/../connection/loader.php';

$db = (new DBConnection())->getDB();
$redis = (new RedisConnection())->getRedis();

echo "PASS SYSTEM Loaded<br>";

$redis->set("pass_test", "PASS Redis OK!");
echo $redis->get("pass_test");
