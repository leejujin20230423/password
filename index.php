<?php
declare(strict_types=1);

// echo "Hello, PASS! This is the refactored index.php.\n";



/**
 * PASS front controller (refactored)
 * - If not logged in -> login screen
 * - If logged in -> role-based landing (existing route kept)
 */

require_once __DIR__ . '/app/bootstrap.php';

use PassApp\Core\SessionVault;

if (!SessionVault::isLoggedIn()) {
    header('Location: /password_0_login/password_0_login_Route/password_0_login_route.php');
    exit;
}

// 기본 진입 화면: 비밀번호 공유현황
header('Location: /password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_route_admin.php');
exit;
