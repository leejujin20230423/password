<?php

return [
    'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '49.247.29.76',
    'name' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'password',   // 실제 DB 이름
    'user' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'lokia',      // 실제 DB 계정
    'pass' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'lokia0528**',// 실제 비밀번호
];
