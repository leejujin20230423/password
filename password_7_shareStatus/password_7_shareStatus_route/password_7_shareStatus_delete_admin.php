<?php
// ==============================================
//  Password 공유현황 - 선택 삭제 처리 (관리자)
//  경로:
//  public/password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_delete_admin.php
// ==============================================

// 1) 세션 시작 및 로그인 체크
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_no'])) {
    header('Location: /password_0_login/password_0_login_View/password_0_login_View.php');
    exit;
}

$currentUserNo = (int)$_SESSION['user_no'];

// 2) POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_route_admin.php');
    exit;
}

// 3) 모드와 선택된 share_id 목록 받기
$mode      = isset($_POST['mode']) ? (string)$_POST['mode'] : '';
$shareIds  = isset($_POST['share_ids']) && is_array($_POST['share_ids']) ? $_POST['share_ids'] : [];

// share_id 정수형으로 정리
$shareIds = array_map('intval', $shareIds);
$shareIds = array_filter($shareIds, function ($v) {
    return $v > 0;
});

// 아무 것도 선택 안 했으면 되돌리기
if (empty($shareIds)) {
    header('Location: /password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_route_admin.php?error=empty');
    exit;
}

// 4) DB 연결
require_once $_SERVER['DOCUMENT_ROOT'] . '/password_60_CRUD/password_60_CRUD.php';

$dbConnection = new DBConnection();
$pdo          = $dbConnection->getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    // IN 절 바인딩용 플레이스홀더 만들기
    $placeholders = [];
    $params       = [
        ':currentUserNo' => $currentUserNo,
    ];

    foreach ($shareIds as $idx => $sid) {
        $ph               = ':id' . $idx;
        $placeholders[]   = $ph;
        $params[$ph]      = $sid;
    }

    // 기본 DELETE 쿼리
    $sql = "DELETE FROM password_share WHERE share_id IN (" . implode(',', $placeholders) . ")";

    // 모드에 따라 권한 조건 추가
    if ($mode === 'by_me') {
        // 내가 공유한 목록에서 삭제 → owner 가 나인 것만
        $sql .= " AND owner_user_no_Fk = :currentUserNo";
    } elseif ($mode === 'to_me') {
        // 내가 공유받은 목록에서 삭제 → target 이 나인 것만
        $sql .= " AND target_user_no_Fk = :currentUserNo";
    } else {
        // 잘못된 모드
        $pdo->rollBack();
        header('Location: /password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_route_admin.php?error=mode');
        exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $pdo->commit();

    // 삭제 후 다시 공유현황 화면으로
    header('Location: /password_7_shareStatus/password_7_shareStatus_route/password_7_shareStatus_route_admin.php?deleted=1');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 개발용 에러 출력 (운영 시에는 로그만 남기고 사용자에게는 일반 메시지로)
    echo "<h3>DB Error (password_7_shareStatus_delete_admin.php)</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}
