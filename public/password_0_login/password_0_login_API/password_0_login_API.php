<?php

// MODEL 로드
require_once $_SERVER['DOCUMENT_ROOT'] . '/password_0_login/password_0_login_Model_Module/password_0_login_Model_Module.php';

// 모바일 체크 함수 (필요하면 나중에 분기용으로 쓰면 됨)
function isMobile() {
    return preg_match('/(android|iphone|ipad|ipod|blackberry|opera mini|windows phone)/i', $_SERVER['HTTP_USER_AGENT']);
}

// POST 요청일 때만 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // HTML 폼의 input name과 정확히 일치해야 함
    $userid   = $_POST['password_admin_userid'] ?? '';
    $password = $_POST['password_admin_pass'] ?? '';

    if ($password_0_login_Model_Module->adminLogin($userid, $password)) {

        // ✅ 로그인 성공 시 이동할 경로 (DocumentRoot = public 기준)
        $redirectUrl = '/password_0_register/password_0_register_View/password_0_register_View_admin/password_0_register_View_admin.php';

        // 화면에 echo 찍지 말고 바로 리다이렉트 (헤더 깨지지 않게)
        header('Location: ' . $redirectUrl);
        exit;

    } else {

        // 로그인 실패 시 로그인 화면으로 이동
        header("Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=1");
        exit;
    }
}
