<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use PassApp\Auth\AuthGate;

// ==============================================
//  비밀번호 공유 설정 저장 (관리자용 라우트)
//  경로:
//  public/password_6_share/password_6_share_route/password_6_share_route_admin.php
// ==============================================

// ------------------------------------------------
// 1) 로그인 체크
// ------------------------------------------------
(new AuthGate())->requireLogin();

// 공유 설정을 만드는 사람(현재 로그인 사용자)
$currentUserNo = (int)$_SESSION['user_no'];

// ------------------------------------------------
// 3) DB 연결 (password_60_CRUD.php 안의 DBConnection 사용)
// ------------------------------------------------
require_once $_SERVER['DOCUMENT_ROOT'] . '/password_60_CRUD/password_60_CRUD.php';

$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();

// 개발 단계에서 에러 내용 바로 보이도록 설정
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ------------------------------------------------
// 4) POST 요청 + action=save_share 만 허용
// ------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /password_6_share/password_6_share_route/password_6_share_route_view_admin.php');
    exit;
}

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
if ($action !== 'save_share') {
    header('Location: /password_6_share/password_6_share_route/password_6_share_route_view_admin.php');
    exit;
}

// ------------------------------------------------
// 5) 폼에서 넘어온 값들 받기
// ------------------------------------------------
$passwordIds   = isset($_POST['password_ids'])    && is_array($_POST['password_ids'])
    ? $_POST['password_ids']
    : [];

$targetUserIds = isset($_POST['target_user_ids']) && is_array($_POST['target_user_ids'])
    ? $_POST['target_user_ids']
    : [];

$shareMemo     = isset($_POST['share_memo'])
    ? trim((string)$_POST['share_memo'])
    : '';

// 정수형으로 변환하고 0 이하 값 제거
$passwordIds   = array_filter(array_map('intval', $passwordIds),   static function ($v) { return $v > 0; });
$targetUserIds = array_filter(array_map('intval', $targetUserIds), static function ($v) { return $v > 0; });

// 유효성 체크: 아무것도 선택 안 했으면 다시 화면으로
if (empty($passwordIds) || empty($targetUserIds)) {
    header('Location: /password_6_share/password_6_share_route/password_6_share_route_view_admin.php?error=empty');
    exit;
}

try {
    // ------------------------------------------------
    // 6) 트랜잭션 시작
    // ------------------------------------------------
    $pdo->beginTransaction();

    // ------------------------------------------------
    // 7) INSERT 준비
    //
    // password_share 테이블 구조(가정):
    //  - share_id (PK, AUTO_INCREMENT)
    //  - owner_user_no_Fk      : 공유 설정을 만든 사람 (로그인 사용자)
    //  - target_user_no_Fk     : 공유를 받는 사용자
    //  - password_idno_Fk      : 공유되는 password 테이블 PK
    //  - share_memo            : 메모 (공통 메모)
    //  - created_at
    //  - updated_at
    //
    //  + UNIQUE KEY uniq_share_owner_target_password
    //      (owner_user_no_Fk, target_user_no_Fk, password_idno_Fk)
    //
    // 같은 조합이 이미 있으면 에러 대신 share_memo / updated_at 만 갱신
    // ------------------------------------------------
    $sql = "
        INSERT INTO password_share (
              owner_user_no_Fk,   -- 공유 설정을 만든 사람 (현재 로그인 유저)
              target_user_no_Fk,  -- 공유를 받는 사용자
              password_idno_Fk,   -- 공유되는 password 테이블 PK
              share_memo,         -- 메모
              created_at,
              updated_at
        ) VALUES (
              :owner_user_no_Fk,
              :target_user_no_Fk,
              :password_idno_Fk,
              :share_memo,
              NOW(),
              NOW()
        )
        ON DUPLICATE KEY UPDATE
              share_memo = VALUES(share_memo),
              updated_at = NOW()
    ";

    $stmt = $pdo->prepare($sql);

    $insertCount = 0;
    $updateCount = 0;

    // ------------------------------------------------
    // 8) 선택된 비밀번호 × 선택된 대상 조합으로 INSERT/UPDATE
    // ------------------------------------------------
    foreach ($passwordIds as $pid) {
        foreach ($targetUserIds as $tuid) {

            $stmt->bindValue(':owner_user_no_Fk',  $currentUserNo, PDO::PARAM_INT);
            $stmt->bindValue(':target_user_no_Fk', $tuid,          PDO::PARAM_INT);
            $stmt->bindValue(':password_idno_Fk',  $pid,           PDO::PARAM_INT);
            $stmt->bindValue(':share_memo',        $shareMemo,     PDO::PARAM_STR);

            $stmt->execute();

            // MySQL 기준:
            //  - 새로 INSERT: rowCount() = 1
            //  - 값 변경 UPDATE: rowCount() = 2
            //  - 값이 동일해서 변경 없음: rowCount() = 0
            $affected = $stmt->rowCount();
            if ($affected === 1) {
                $insertCount++;
            } elseif ($affected === 2) {
                $updateCount++;
            }
        }
    }

    // ------------------------------------------------
    // 9) 커밋
    // ------------------------------------------------
    $pdo->commit();

    // ------------------------------------------------
    // 10) 완료 후 다시 공유 화면으로 리디렉트
    //      (원하면 insert/update 개수도 쿼리스트링으로 전달)
    // ------------------------------------------------
    $redirectUrl = '/password_6_share/password_6_share_route/password_6_share_route_view_admin.php'
        . '?success=1'
        . '&insert=' . $insertCount
        . '&update=' . $updateCount;

    header('Location: ' . $redirectUrl);
    exit;

} catch (PDOException $e) {

    // 에러 시 롤백
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 개발 단계에서는 에러 메세지 출력해서 원인 파악
    // 실제 서비스에서는 로그에만 남기고 사용자에게는 친절한 메세지 출력
    echo "<h3>DB Error (password_6_share_route_admin.php)</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}
