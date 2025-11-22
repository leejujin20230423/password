<?php

// .env 에서 읽어온 값은 $_ENV 에 들어 있다고 가정
return [
    "host" => $_ENV["DB_HOST"] ?? null,
    "name" => $_ENV["DB_NAME"] ?? null,
    "user" => $_ENV["DB_USER"] ?? null,
    "pass" => $_ENV["DB_PASS"] ?? null,
];