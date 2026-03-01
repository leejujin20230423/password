<?php
declare(strict_types=1);

/**
 * PASS modern bootstrap
 * - Loads legacy loader.php (dotenv, etc.)
 * - Registers PSR-4-like autoloader for /app
 * - Provides shared service singletons (PDO, Redis, session)
 */

require_once __DIR__ . '/../app_bootstrap.php'; // keeps pass_require_loader_or_die() compatibility
pass_require_loader_or_die();

use PassApp\Core\DbHub;
use PassApp\Core\CacheHub;
use PassApp\Core\SessionVault;

// Autoload (very small, no composer required)
spl_autoload_register(function (string $class): void {
    $prefix = 'PassApp\\';
    $baseDir = __DIR__ . '/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Boot core services (idempotent)
SessionVault::boot();
DbHub::warm();
CacheHub::warm();
