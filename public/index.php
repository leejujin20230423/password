<?php

// 공통 로더 (DB, Redis 등 초기화)
require_once __DIR__ . '/../connection/loader.php';

$db    = (new DBConnection())->getDB();
$redis = (new RedisConnection())->getRedis();

// ---------------------------------------------------------------------
// 1. 환경(로컬/서버) 판별
//    - 로컬: php -S localhost:8899 같은 경우 → HTTP_HOST 가 localhost:8899 / 127.0.0.1:포트
//    - 서버: pass.bizstore.co.kr 같은 실제 도메인
// ---------------------------------------------------------------------
$host = $_SERVER['HTTP_HOST'] ?? '';

$isLocal =
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    str_starts_with($host, 'localhost:') ||
    str_starts_with($host, '127.0.0.1:');

// 로그인 뷰 실제 파일 경로(파일 시스템 기준)
$loginViewFile = __DIR__ . '/../password_0_login/password_0_login_View/password_0_login_View.php';

// 로그인 뷰 URL 경로(웹 기준, 서버에서 사용)
$loginViewUrl  = '/password_0_login/password_0_login_View/password_0_login_View.php';

// ---------------------------------------------------------------------
// 2. 로컬일 때와 서버일 때 분리
// ---------------------------------------------------------------------
if ($isLocal) {
    // ✅ 로컬 개발 환경 (예: php -S localhost:8899)
    // - DocumentRoot 가 /.../pass 또는 /.../pass/public 여서
    //   URL 리다이렉트가 꼬일 수 있으므로
    //   "파일을 직접 include" 해서 화면만 로드

    if (file_exists($loginViewFile)) {
        require_once $loginViewFile;
        exit;
    } else {
        // 혹시 경로 잘못됐을 때 에러 확인용
        echo '로그인 뷰 파일을 찾을 수 없습니다: ' . htmlspecialchars($loginViewFile);
        exit;
    }

} else {
    // ✅ 서버 환경 (예: pass.bizstore.co.kr)
    // - 웹 루트가 pass (또는 pass/public) 라고 가정하고
    //   URL 기준으로 로그인 화면으로 리다이렉트
    header('Location: ' . $loginViewUrl);
    exit;
}
