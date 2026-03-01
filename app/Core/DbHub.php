<?php
declare(strict_types=1);

namespace PassApp\Core;

use PDO;
use PDOException;

final class DbHub
{
    private static ?PDO $pdo = null;

    public static function warm(): void
    {
        self::pdo();
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // NOTE: $_ENV can be empty depending on PHP/FPM configuration.
        // Use EnvBox::get() for consistent behavior across environments.
        $host = EnvBox::get('DB_HOST', '127.0.0.1');
        $db   = EnvBox::get('DB_NAME', 'pass');
        $user = EnvBox::get('DB_USER', 'root');
        $pass = EnvBox::get('DB_PASS', '');
        $port = EnvBox::get('DB_PORT', '3306');
        $charset = EnvBox::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Fail fast with a readable message (no HTML)
            http_response_code(500);
            echo "DB 연결 실패: " . $e->getMessage();
            exit;
        }

        return self::$pdo;
    }
}
