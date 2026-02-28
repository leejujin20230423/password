<?php
/**
 * index.php
 * -----------------------------------------
 * - 항상 로그인 화면만 보여주던 기존 구조에서
 *   세션 상태에 따라:
 *   1) 비로그인  → 로그인 화면 출력
 *   2) 로그인    → 공지(Notice) or 권한별 메인 화면으로 이동
 */

// ==========================================================
// 0. 공통 로더 (DB, Redis 등 초기화)
// ==========================================================
require_once __DIR__ . '/app_bootstrap.php';
pass_require_loader_or_die();

// DB / Redis 연결 (실패 시 loader 또는 각 클래스에서 에러 처리)
$pdoConnection   = (new DBConnection())->getDB();
$redisConnection = (new RedisConnection())->getRedis();

// ==========================================================
// 1. 세션 시작
// ==========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================================
// 2. 로그인 여부 확인
//    - is_logged_in, userid, user_no 등 상황에 따라 사용
// ==========================================================
$isUserAuthenticated = false;

// 기존 로그인 처리에서 세션을 어떻게 세팅했는지에 따라 조합
if (!empty($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    $isUserAuthenticated = true;
} elseif (!empty($_SESSION['user_no']) || !empty($_SESSION['userid'])) {
    // 혹시 is_logged_in 없이 user_no / userid 만 세팅되는 경우도 대비
    $isUserAuthenticated = true;
}

// ==========================================================
// 3. 로그인 안 된 경우 → 로그인 뷰 그대로 로드
// ==========================================================
if (!$isUserAuthenticated) {

    // 로그인 뷰 파일 경로 (파일 시스템 기준)
    $loginViewFile = __DIR__ . '/password_0_login/password_0_login_View/password_0_login_View.php';

    if (file_exists($loginViewFile)) {
        require_once $loginViewFile;
        exit;
    } else {
        // 경로가 잘못되었을 때 확인용
        echo '로그인 뷰 파일을 찾을 수 없습니다: ' . htmlspecialchars($loginViewFile, ENT_QUOTES, 'UTF-8');
        exit;
    }
}

// ==========================================================
// 4. 로그인 된 경우 → 공지 노출 여부에 따라 분기
//    - $_SESSION['show_notice'] 는 로그인 처리 시 설정했다고 가정
//    - $_SESSION['after_notice_url'] 도 로그인 처리에서 세팅됨
// ==========================================================

// 이번 요청에서 공지를 보여줄지 여부 (기본값: false)
$shouldShowNoticeOnce = !empty($_SESSION['show_notice']) && $_SESSION['show_notice'] === true;

// 권한별로 지정된 공지 이후 이동 URL (없으면 기본값 세팅)
if (!empty($_SESSION['after_notice_url'])) {
    $redirectAfterNoticeUrl = (string)$_SESSION['after_notice_url'];
} else {
    // 혹시 세션에 없다면 user_type 기준으로 백업 경로 설정
    $currentUserType = isset($_SESSION['user_type']) ? (string)$_SESSION['user_type'] : 'user';

    switch ($currentUserType) {
        case 'master':
            $redirectAfterNoticeUrl =
                '/password_0_register/password_0_register_View/password_0_register_View_master/password_0_register_View_master.php';
            break;

        case 'admin':
            $redirectAfterNoticeUrl =
                '/password_0_register/password_0_register_View/password_0_register_View_admin/password_0_register_View_admin.php';
            break;

        case 'user':
        default:
            $redirectAfterNoticeUrl =
                '/password_0_register/password_0_register_View/password_0_register_View_user/password_0_register_View_user.php';
            break;
    }

    // 세션에도 다시 저장해두면 이후에도 동일하게 사용 가능
    $_SESSION['after_notice_url'] = $redirectAfterNoticeUrl;
}

// ==========================================================
// 5. 최종 분기
//    - show_notice = true  → 공지 페이지로 이동
//    - show_notice = false → 권한별 메인(등록 화면)으로 이동
// ==========================================================

if ($shouldShowNoticeOnce) {
    // 공지 페이지 경로 (현재 admin용 고정)
    $noticeUrl = '/password_0_notice/password_0_notice_view/password_0_notice_view_admin/password_0_notice_view_admin.php';

    header('Location: ' . $noticeUrl);
    exit;
} else {
    // 공지 노출 대상이 아니면 바로 메인으로
    header('Location: ' . $redirectAfterNoticeUrl);
    exit;
}
