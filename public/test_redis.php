<?php

// public/test_redis.php 기준으로 한 폴더 위로 올라가서(connection) 진입
require __DIR__ . '/../connection/loader.php';

// loader.php 에서 이미 RedisConnection 클래스를 require_once 해준 상태라고 가정
$redis = (new RedisConnection())->getRedis();

$redis->set('pass_test', 'OK');
echo $redis->get('pass_test');
