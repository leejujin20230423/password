<?php
/**
 * password_0_login_5_process_view_admin.php
 * -----------------------------------------
 * 1. 아이디/비밀번호 검증이 끝난 상태에서 이 파일로 넘어온다고 가정
 * 2. $loginSuccess == true 이면 로그인 성공, $user 배열에 users 테이블 정보가 담겨 있음
 * 3. 로그인 성공 시:
 *    - 세션에 사용자 정보 세팅
 *    - 권한별 after_notice_url 세팅
 *    - 무조건 "안내(공지) 페이지"로 먼저 이동
 * 4. 안내 페이지에서 notice_view_count 5회까지만 노출 처리
 */

// (예시) $loginSuccess, $user 가 이미 앞에서 세팅되어 있다고 가정

if ($loginSuccess) {

    /**
     * ==========================================================
     * 1. 세션 시작
     * ==========================================================
     */
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    /**
     * ==========================================================
     * 2. 로그인한 사용자 정보 세션에 저장
     * ==========================================================
     */
    // users 테이블의 PK (예: user_no) - 안내 페이지에서 카운트 업데이트 시 사용
    $userNo = isset($user['user_no']) ? (int)$user['user_no'] : 0;

    $_SESSION['is_logged_in'] = true;
    $_SESSION['user_no']      = $userNo; // 🔴 꼭 세션에 저장해 둬야 안내 페이지에서 사용 가능
    $_SESSION['userid']       = isset($user['userid']) ? (string)$user['userid'] : '';
    $_SESSION['username']     = isset($user['username']) ? (string)$user['username'] : '';
    $_SESSION['user_type']    = isset($user['user_type']) ? (string)$user['user_type'] : ''; // master / admin / user 등

    /**
     * ==========================================================
     * 3. 공지 이후에 이동할 URL 권한별로 세팅
     *    - 안내 페이지에서 notice_view_count 검사 후 이 URL로 보내게 됨
     * ==========================================================
     */
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

    /**
     * ==========================================================
     * 4. 최종 리다이렉트
     *    - 로그인 성공 시에는 항상 안내(공지) 페이지로 이동
     *    - 안내 페이지 상단에서 notice_view_count 를 체크해서
     *      5회 이상이면 바로 after_notice_url 로 보내도록 처리
     * ==========================================================
     */

    header('Location: /password_0_notice/password_0_notice_view/password_0_notice_view_admin/password_0_notice_view_admin.php');
    exit;

} else {
    /**
     * ==========================================================
     * 로그인 실패 시 처리
     * ==========================================================
     */
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php?error=login');
    exit;
}
