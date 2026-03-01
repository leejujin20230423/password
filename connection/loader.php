<?php
/**
 * PASS bootstrap loader
 * (프로젝트마다 DB/ENV 로드용으로 로컬에서만 두는 경우가 많음)
 */

$ROOT = dirname(__DIR__);

// Lightweight .env loader (works even when $_ENV is not populated by PHP/FPM)
require_once $ROOT . '/app/Core/EnvBox.php';
\PassApp\Core\EnvBox::boot($ROOT . '/.env');

// composer autoload가 있으면 로드
$autoload = $ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// .env 로딩 (phpdotenv 사용 시)
if (class_exists(\Dotenv\Dotenv::class) && file_exists($ROOT.'/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($ROOT);
    $dotenv->safeLoad();
}

/**
 * 여기부터는 프로젝트가 기대하는 값에 맞춰 조정
 * - DB 연결을 여기서 만들거나
 * - 상수/전역 변수 세팅만 해도 됨
 */

// 예: 기본 환경변수 세팅(없어도 동작은 하게)
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'local';

// 필요하면 PDO 연결 (프로젝트가 DB를 쓰는 경우만)
// 아래는 "있으면" 생성하는 형태라 당장 에러는 안 남
if (!isset($GLOBALS['pdo']) && !empty($_ENV['DB_NAME'])) {
    $host = \PassApp\Core\EnvBox::get('DB_HOST', '127.0.0.1');
    $db   = \PassApp\Core\EnvBox::get('DB_NAME', '');
    $user = \PassApp\Core\EnvBox::get('DB_USER', 'root');
    $pass = \PassApp\Core\EnvBox::get('DB_PASS', '');
    $port = \PassApp\Core\EnvBox::get('DB_PORT', '3306');
    $charset = \PassApp\Core\EnvBox::get('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    try {
        $GLOBALS['pdo'] = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        // 로컬에서 DB 없을 때도 화면은 뜨게(필요하면 여기서 die로 바꿔도 됨)
        // error_log($e->getMessage());
    }
}

return true;
require_once __DIR__ . '/DBConnection.php';
require_once __DIR__ . '/RedisConnection.php';
