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

    // ✅ 로그인 시도
    if ($password_0_login_Model_Module->adminLogin($userid, $password)) {

        // ✅ 세션 시작 (adminLogin 안에서 이미 했어도 문제 없음)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // (선택) 여기서도 로그인 플래그를 한 번 더 확실히 잡아줄 수 있음
        if (!isset($_SESSION['is_logged_in'])) {
            $_SESSION['is_logged_in'] = true;
        }

        // ✅ 공지 후에 이동할 "비밀번호 등록 Route" URL을 세션에 저장
        //    나중에 공지 페이지에서 이 값을 보고 이동
        $_SESSION['after_notice_url'] = '/password_5_passwordRegister/password_5_passwordRegister_Route/password_5_passwordRegister_Route.php';

        // ✅ 로그인 성공 시, 이제는 바로 등록화면이 아니라
        //    "공지/메뉴얼 (관리자용)" 화면으로 먼저 이동
        $redirectUrl = '/password_0_notice/password_0_notice_view/password_0_notice_view_admin/password_0_notice_view_admin.php';

        // 화면에 echo 찍지 말고 바로 리다이렉트 (헤더 깨지지 않게)
        header('Location: ' . $redirectUrl);
        exit;

    } else {

        // 로그인 실패 시 로그인 화면으로 이동
        header("Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=1");
        exit;
    }
}
