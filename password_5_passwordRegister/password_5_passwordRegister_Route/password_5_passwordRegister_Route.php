<?php

/**
 * password_5_passwordRegister_Route.php
 * 
 * 로그인 후 진입하는 라우터:
 *  - user_type === 'admin' → 관리자 등록 화면
 *  - user_type === 'user'  → 사용자 등록 화면
 */

// 공통 로더 (DB, ENV, Redis 등)
require_once dirname(__DIR__, 2) . '/app_bootstrap.php';
pass_require_loader_or_die();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 로그인 안 되어 있으면 바로 로그인 화면으로
if (empty($_SESSION['user_no'])) {
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

// 우선 세션에서 user_type 가져오기
$userType = $_SESSION['user_type'] ?? null;

// 세션에 user_type 이 없으면 DB에서 한 번 더 확인 (안전장치)
if ($userType === null) {
    try {
        $db = (new DBConnection())->getDB();

        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $_SESSION['user_no'], PDO::PARAM_INT);
        $stmt->execute();

        if ($row = $stmt->fetch()) {
            $userType = $row['user_type'] ?? null;
            // 한 번 가져온 건 세션에 다시 저장
            $_SESSION['user_type'] = $userType;
        }
    } catch (Throwable $e) {
        // DB 에러 시에는 로그인으로 돌려보내는 쪽으로 처리
        header('Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=db');
        exit;
    }
}

// 최종 분기
switch ($userType) {
    case 'master':
        // ✅ 마스터 전용 등록 화면
        header('Location: /password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_master/password_5_passwordRegister_View_master.php');
        exit;

    case 'admin':
        // ✅ 관리자 전용 등록 화면
        header('Location: /password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_admin/password_5_passwordRegister_View_admin.php');
        exit;

    case 'user':
        // ✅ 일반 사용자 전용 등록 화면
        header('Location: /password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_user/password_5_passwordRegister_View_user.php');
        exit;

    default:
        // 알 수 없는 권한이면 세션 정리 후 로그인 화면으로
        session_unset();
        session_destroy();
        header('Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=permission');
        exit;
}
