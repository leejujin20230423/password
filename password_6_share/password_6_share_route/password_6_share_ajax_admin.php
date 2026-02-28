<?php
// public/password_6_share/password_6_share_route/password_6_share_ajax_admin.php

// =========================================
// 1. 세션 시작 & 로그인 체크
// =========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// 로그인 여부 확인
if (empty($_SESSION['user_no'])) {
    echo json_encode([
        'ok'  => false,
        'msg' => '로그인이 필요합니다.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================
// 2. DB 연결 (password_60_CRUD 에 있는 DBConnection 재사용)
// =========================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/password_60_CRUD/password_60_CRUD.php';

try {
    $dbConnection = new DBConnection();
    $pdo          = $dbConnection->getDB();
} catch (Exception $e) {
    echo json_encode([
        'ok'  => false,
        'msg' => 'DB 연결 실패: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================
// 3. action 분기
// =========================================
$action = $_POST['action'] ?? '';

if ($action === 'search_user') {

    // ----------------------------
    // 3-1) 전화번호로 users 검색
    // ----------------------------
    $rawPhone = $_POST['phone'] ?? '';
    $rawPhone = trim($rawPhone);

    if ($rawPhone === '') {
        echo json_encode([
            'ok'  => false,
            'msg' => '전화번호를 입력해 주세요.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 숫자만 추출 (010-1234-5678 → 01012345678)
    $digits = preg_replace('/\D+/', '', $rawPhone);
    if ($digits === '') {
        echo json_encode([
            'ok'  => false,
            'msg' => '전화번호 형식이 올바르지 않습니다.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * ⚠ 여기서 "users" 테이블의 전화번호 컬럼명을 실제와 맞춰야 한다.
     *  - 예: phone / user_phone / mobile 등
     *  아래 예시는 phone 이라고 가정:
     *    - users.user_no (PK)
     *    - users.username
     *    - users.phone (전화번호)
     */

    $sql = "
        SELECT user_no, username, phone
        FROM users
        WHERE REPLACE(phone, '-', '') = :phone_digits
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':phone_digits', $digits, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'ok'   => true,
            'user' => [
                'user_no'  => (int)$user['user_no'],
                'username' => (string)$user['username'],
                'phone'    => (string)($user['phone'] ?? '')
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        echo json_encode([
            'ok'  => false,
            'msg' => '해당 전화번호로 등록된 회원이 없습니다.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 그 외 지원하지 않는 액션
echo json_encode([
    'ok'  => false,
    'msg' => '잘못된 요청입니다.'
], JSON_UNESCAPED_UNICODE);
exit;
