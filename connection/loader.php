<?php
declare(strict_types=1);

/**
 * PASS bootstrap loader
 * - .env 로딩을 확실히 하고(EnvBox),
 * - 필요한 경우 전역 PDO($GLOBALS['pdo'])를 생성
 * - 절대 실행 안 되는 코드/중복 로딩 제거
 */

$ROOT = dirname(__DIR__);

// 1) .env 로드 (EnvBox 하나로 통일)
require_once $ROOT . '/app/Core/EnvBox.php';
\PassApp\Core\EnvBox::boot($ROOT . '/.env');

// 2) composer autoload (있으면)
$autoload = $ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// 3) 기본 ENV (없으면 기본값)
$appEnv = \PassApp\Core\EnvBox::get('APP_ENV', 'local');
putenv("APP_ENV={$appEnv}");
$_ENV['APP_ENV'] = $appEnv;
$_SERVER['APP_ENV'] = $appEnv;

// 4) 전역 PDO 생성 (DB_NAME 없어도 만들지 않음)
if (!isset($GLOBALS['pdo'])) {
    $host    = \PassApp\Core\EnvBox::get('DB_HOST', '127.0.0.1');
    $db      = \PassApp\Core\EnvBox::get('DB_NAME', '');
    $user    = \PassApp\Core\EnvBox::get('DB_USER', 'root');
    $pass    = \PassApp\Core\EnvBox::get('DB_PASS', '');
    $port    = \PassApp\Core\EnvBox::get('DB_PORT', '3306');
    $charset = \PassApp\Core\EnvBox::get('DB_CHARSET', 'utf8mb4');

    if ($db !== '') {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        try {
            $GLOBALS['pdo'] = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (Throwable $e) {
            // 운영에서 원인 파악 가능하도록 로그는 남김
            error_log('[PASS][DB] connect failed: ' . $e->getMessage());

            // 로그인/서비스가 DB 필수면 fail-fast 추천
            // http_response_code(500);
            // exit('DB 연결 실패');
        }
    }
}

// 필요 시 RedisConnection 등은 여기서 로드 (return 아래로 보내지 말 것)
require_once __DIR__ . '/DBConnection.php';
require_once __DIR__ . '/RedisConnection.php';

if (!isset($GLOBALS['redis'])) {
    try {
        $GLOBALS['redis'] = (new RedisConnection())->getRedis();
    } catch (Throwable $e) {
        $GLOBALS['redis'] = null;
    }
}

return true;
