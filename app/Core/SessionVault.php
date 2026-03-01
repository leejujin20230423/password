<?php
declare(strict_types=1);

namespace PassApp\Core;

final class SessionVault
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) return;

        if (session_status() === PHP_SESSION_NONE) {
            $saveDir = dirname(__DIR__, 2) . '/session';
            if (!is_dir($saveDir)) {
                @mkdir($saveDir, 0770, true);
            }
            if (is_dir($saveDir) && is_writable($saveDir)) {
                session_save_path($saveDir);
            }

            // Safer defaults (works without HTTPS too)
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]);

            session_start();
        }

        self::$booted = true;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function pull(string $key, mixed $default=null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function drop(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_no']);
    }

    public static function role(): string
    {
        return (string)($_SESSION['user_level'] ?? '');
    }

    public static function nuke(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'] ?? '/', $p['domain'] ?? '', (bool)($p['secure'] ?? false), (bool)($p['httponly'] ?? true));
        }
        session_destroy();
    }
}
