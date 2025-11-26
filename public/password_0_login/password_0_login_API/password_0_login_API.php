<?php


// MODEL 로드
require_once $_SERVER['DOCUMENT_ROOT'] . '/password_0_login/password_0_login_Model_Module/password_0_login_Model_Module.php';

// 모바일 체크 함수
function isMobile() {
    return preg_match('/(android|iphone|ipad|ipod|blackberry|opera mini|windows phone)/i', $_SERVER['HTTP_USER_AGENT']);
}

// POST 요청일 때만 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // HTML 폼의 input name과 정확히 일치해야 함
    $userid   = $_POST['password_admin_userid'] ?? '';
    $password = $_POST['password_admin_pass'] ?? '';

    if ($password_0_login_Model_Module->adminLogin($userid, $password)) {

        // 로그인 성공
        // 🔥 → 존재하는 경로로 이동해야 한다 !!
        // pass_main은 없으므로 index.php 로 이동 처리

        echo "api 페이지에서 로그인 성공후 다른곳으로 이동준비";
        // header("Location: /index.php");
        exit;

    } else {

        // 로그인 실패 시 로그인 화면으로 이동
        header("Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=1");
        exit;
    }
}
