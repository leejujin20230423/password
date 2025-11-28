<?php
// password_0_login_5_process_view_admin.php (예시)

// 아이디/비밀번호 검사 끝난 뒤라고 가정
// 예시: $user 에 DB에서 가져온 사용자 정보가 들어있고,
//       $loginSuccess 가 true 이면 로그인 성공
if ($loginSuccess) {

    // 세션 시작
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 로그인한 사용자 정보 세팅 (필요에 따라 키 이름 맞춰서 사용)
    $_SESSION['is_logged_in'] = true;
    $_SESSION['userid']       = $user['userid'];     // 로그인 ID (예시)
    $_SESSION['username']     = $user['username'];   // 화면에 표시할 이름
    $_SESSION['user_type']    = $user['user_type'];  // master / admin / user 등

    // ✅ 공지 이후에 어디로 보낼지 URL을 세션에 저장
    //    권한별 비밀번호 등록 페이지 경로 분기 (switch)
    switch ($_SESSION['user_type']) {
        case 'master':
            $_SESSION['after_notice_url'] =
                '/password_0_register/password_0_register_View/password_0_register_View_master/password_0_register_View_master.php';
            break;

        case 'admin':
            $_SESSION['after_notice_url'] =
                '/password_0_register/password_0_register_View/password_0_register_View_admin/password_0_register_View_admin.php';
            break;

        case 'user':
        default:
            $_SESSION['after_notice_url'] =
                '/password_0_register/password_0_register_View/password_0_register_View_user/password_0_register_View_user.php';
            break;
    }

    // ✅ 이제는 곧바로 등록 화면으로 가지 말고
    //    공지/메뉴얼(ADMIN용) 페이지로 먼저 이동
    header('Location: /password_0_notice/password_0_notice_view/password_0_notice_view_admin/password_0_notice_view_admin.php');
    exit;

} else {
    // 로그인 실패 시 처리 (예: 다시 로그인 화면으로)
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=login');
    exit;
}
