<?php

require_once __DIR__ . '/../app/Core/EnvBox.php';

use PassApp\Core\EnvBox;

class DBConnection
{
    private ?PDO $pdo = null;

    public function __construct()
    {
        // Ensure .env is loaded even if the runtime doesn't populate $_ENV.
        EnvBox::boot(dirname(__DIR__) . '/.env');

        $host = EnvBox::get('DB_HOST', '127.0.0.1');
        $db   = EnvBox::get('DB_NAME', 'pass');
        $user = EnvBox::get('DB_USER', 'root');
        $pass = EnvBox::get('DB_PASS', '');
        $port = EnvBox::get('DB_PORT', '3306');
        $charset = EnvBox::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function getDB(): PDO
    {
        return $this->pdo;
    }
}
