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
// DB는 실제 사용 시점에 lazy connect (초기 화면 로딩 안정화)
try {
    CacheHub::warm();
} catch (Throwable $e) {
    error_log('[PASS][bootstrap] cache warm failed: ' . $e->getMessage());
}
