<?php
declare(strict_types=1);

namespace PassApp\Core;

/**
 * EnvBox
 * - Loads .env without requiring external libraries.
 * - Provides a single, consistent read path via EnvBox::get().
 *
 * Why:
 * - $_ENV may be empty depending on php.ini / FPM / Apache config.
 * - getenv() may also be empty if variables are not exported.
 * - This loader writes to getenv/$_ENV/$_SERVER to reduce environment variance.
 */
final class EnvBox
{
    private static bool $booted = false;

    public static function boot(string $envPath): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        if (!is_file($envPath)) {
            return;
        }

        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Only set if not already defined by the environment
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return (string)$v;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string)$_SERVER[$key];
        }
        return $default;
    }
}
