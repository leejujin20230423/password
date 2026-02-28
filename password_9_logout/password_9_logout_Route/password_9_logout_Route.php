<?php
// password_9_logout_Route.php
// 역할: user_type 에 따라 세션을 정리하고, 각자 로그인 페이지로 보내는 공통 로그아웃 라우터

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 공통 로그아웃 처리 + 리다이렉트 함수
 *
 * @param string $redirectUrl 로그아웃 후 이동할 URL
 */
function password_clear_session_and_redirect(string $redirectUrl): void
{
    // 1) 모든 세션 변수 비우기
    $_SESSION = array();

    // 2) 세션 쿠키까지 제거 (선택 사항이지만 보통 같이 함)
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // 3) 세션 파괴
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // 4) 최종 이동
    header('Location: ' . $redirectUrl);
    exit;
}

/**
 * (관리자 전용) Redis 전체 삭제
 * - 기존 동작 유지: flushAll()
 */
function password_admin_flush_redis_all(): void
{
    // 프로젝트 구조에 맞게 경로 유지
    require_once __DIR__ . '/../../../connection/classes/redis_stubs.php';

    try {
        if (!class_exists('Redis')) {
            error_log('[LOGOUT] Redis class not found');
            return;
        }

        $redisClient = new Redis();
        $redisClient->connect('127.0.0.1', 6379, 0.5);

        // 🔥 전체 Redis 포맷 (모든 DB, 모든 키 삭제)
        $redisClient->flushAll();
        error_log('[LOGOUT] Redis flushAll() called');

    } catch (Exception $e) {
        error_log('[LOGOUT] Redis error: ' . $e->getMessage());
    }
}

// ✅ 세션에서 user_type 가져오기
//   예) 로그인 시 $_SESSION['user_type'] = 'admin'; 이런 식으로 저장되어 있다고 가정
$currentUserType = isset($_SESSION['user_type']) ? (string)$_SESSION['user_type'] : '';

// ✅ 디버깅이 필요하면 잠깐 이렇게 확인해도 됨
// header('Content-Type: text/plain; charset=utf-8');
// echo "여기는 password_9_logout_Route.php 입니다.\n";
// echo "현재 user_type: " . var_export($userType, true);
// exit;

// 🔁 기본 리다이렉트 URL (예: 일반 사용자 로그인 화면)
$redirectToLoginUrl = '/password_9_login/password_9_login_View/password_9_login_View_user/password_9_login_View_user.php';

// ✅ user_type 에 따라 분기 (switch 방식)
switch ($currentUserType) {
    case 'master':
        // 마스터 전용 로그인 페이지
        $redirectToLoginUrl = '/password_9_login/password_9_login_View/password_9_login_View_master/password_9_login_View_master.php';
        break;

    case 'admin':

        // 관리자 로그아웃 시: Redis 전체 삭제(기존 동작 유지)
        password_admin_flush_redis_all();

        // 관리자 전용 로그인 페이지
        $redirectToLoginUrl = '/password_9_login/password_9_login_View/password_9_login_View_admin/password_9_login_View_admin.php';
        break;

    case 'user':
        // 일반 사용자 전용 로그인 페이지
        $redirectToLoginUrl = '/password_9_login/password_9_login_View/password_9_login_View_user/password_9_login_View_user.php';
        break;

    default:
        // user_type이 없거나 이상한 값이면: 공통/기본 로그인으로
        // 위에서 기본값을 이미 user용으로 잡아둠
        break;
}

// 최종 로그아웃 처리 + 이동
// - 아래 함수에서 세션/쿠키/파괴를 한 번만 처리
password_clear_session_and_redirect($redirectToLoginUrl);
