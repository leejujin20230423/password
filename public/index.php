<?php

// 공통 로더 (DB, Redis 등 초기화)
require_once __DIR__ . '/../connection/loader.php';

// DB / Redis 연결 (실패 시 loader 또는 각 클래스에서 에러 처리)
$db    = (new DBConnection())->getDB();
$redis = (new RedisConnection())->getRedis();

// ---------------------------------------------------------------------
// 1. 로그인 뷰 파일 경로 지정 (파일 시스템 기준)
//    - public/ 바깥에 있는 뷰를 직접 include 해서 출력
// ---------------------------------------------------------------------
$loginViewFile = __DIR__ . '/../password_0_login/password_0_login_View/password_0_login_View.php';

// ---------------------------------------------------------------------
// 2. 리다이렉트 없이 "항상" 뷰 파일을 직접 로드
//    - 로컬이든 서버든 공통 동작
// ---------------------------------------------------------------------
if (file_exists($loginViewFile)) {
    require_once $loginViewFile;
    exit;
} else {
    // 경로가 잘못되었을 때 확인용
    echo '로그인 뷰 파일을 찾을 수 없습니다: ' . htmlspecialchars($loginViewFile);
    exit;
}
