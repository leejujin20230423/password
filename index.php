<?php
declare(strict_types=1);

echo "Hello, PASS! This is the refactored index.php.\n";



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

// 기존 화면 흐름 유지: "비밀번호 등록" 라우트로 이동
header('Location: /password_5_passwordRegister/password_5_passwordRegister_Route/password_5_passwordRegister_Route.php');
exit;
