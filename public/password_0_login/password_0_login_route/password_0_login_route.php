<?php
// password_0_login_Route.php

// 1. 현재 요청된 URL에서 쿼리스트링을 제외한 "경로"만 가져옴
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 2. 양쪽의 / 를 제거하고 / 기준으로 나눔
//    예: /password_0_login/password_0_login_View.php
//        → ["password_0_login", "password_0_login_View.php"]
$segments = explode('/', trim($path, '/'));

// 3. 마지막 조각 (파일명 역할 하는 부분)
$last = end($segments);

// 4. 마지막 조각 값에 따라 분기
switch ($last) {

    // URL 마지막이 password_0_login_View.php 이면 login.php로 이동
    case 'password_0_login_View.php':
        header('Location: /password_0_login/login.php');
        exit;

    // 예시: 회원가입 화면 같은 다른 뷰들도 여기서 분기 가능
    case 'password_0_join_View.php':
        header('Location: /password_0_login/join.php');
        exit;

    // 더 필요한 경우 여기에 case 계속 추가
    // case 'xxx_View.php':
    //     header('Location: /어디/어디.php');
    //     exit;

    // 어느 case에도 안 걸리면 기본 동작
    default:
        // 예: 메인으로 보내기
        header('Location: /');
        exit;
}
