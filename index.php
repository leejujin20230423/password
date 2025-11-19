<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 현재 파일 위치 기준으로 connection.php 로드
require_once __DIR__ . '/connection/connection.php';

// 로그인 여부에 따라 분기
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 로그인 되어 있으면 → 메인 페이지 (dashboard 또는 원하는 페이지)
if (!empty($_SESSION['user_id'])) {
    // 원하는 페이지로 이동

    echo "여기로 이동 시도";

    echo "<pre>";
    print_r($_SESSION['user_id']);
    echo "</pre>";

    header("Location: /password_0_login/password_0_login_View/password_0_login_View.php");

    exit;
}

// 로그인 안 되어 있으면 → 로그인 페이지로 이동
header("Location: /password_0_login/password_0_login_View/password_0_login_View.php");
exit;
