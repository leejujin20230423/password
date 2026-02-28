<?php

// ==========================================================
// password_0_login_API.php
// ----------------------------------------------------------
// - 로그인 폼 POST 처리
// - 세션/리다이렉트 헤더가 깨지지 않도록 출력 없이 동작
// ==========================================================

// MODEL 로드 (기존 경로 유지)
require_once dirname(__DIR__, 2) . '/password_0_login_Model_Module/password_0_login_Model_Module.php';

/**
 * 모바일 체크 함수
 * - 현재는 분기용으로만 남겨둠
 */
function password_is_mobile_device(): bool
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match('/(android|iphone|ipad|ipod|blackberry|opera mini|windows phone)/i', $userAgent);
}

// POST 요청일 때만 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // HTML 폼의 input name과 정확히 일치해야 함
    $inputUserId   = trim((string)($_POST['password_admin_userid'] ?? ''));
    $inputPassword = (string)($_POST['password_admin_pass'] ?? '');

    // ✅ 로그인 시도 (기존 메서드명 유지: adminLogin)
    $isAuthSuccess = $password_0_login_Model_Module->adminLogin($inputUserId, $inputPassword);

    if ($isAuthSuccess) {

        // ✅ 세션 시작 (adminLogin 안에서 이미 했어도 안전)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ✅ 로그인 안정화: 세션 고정 공격 방지
        if (!headers_sent()) {
            @session_regenerate_id(true);
        }

        // ✅ 로그인 플래그/공지 플래그를 일관되게 세팅
        $_SESSION['is_logged_in'] = true;
        $_SESSION['show_notice']  = true;

        // ✅ 공지 이후 이동할 URL (기존 값 유지)
        $_SESSION['after_notice_url'] = '/password_5_passwordRegister/password_5_passwordRegister_Route/password_5_passwordRegister_Route.php';

        // ✅ 로그인 성공 시 공지(관리자용)으로 이동
        $redirectToUrl = '/password_0_notice/password_0_notice_view/password_0_notice_view_admin/password_0_notice_view_admin.php';

        header('Location: ' . $redirectToUrl);
        exit;
    }

    // 로그인 실패 시 로그인 화면으로 이동
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=1');
    exit;
}
