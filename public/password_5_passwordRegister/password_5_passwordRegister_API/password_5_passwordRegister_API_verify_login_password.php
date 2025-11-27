<?php
require_once __DIR__ . '/../../../connection/classes/DBConnection.php';
require_once __DIR__ . '/../../../SessionManager/SessionManager.php';

SessionManager::start();
header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_no'])) {
    echo json_encode(['ok' => false, 'msg' => '로그인이 필요합니다.']);
    exit;
}

$loginPassword = $_POST['login_password'] ?? '';
if ($loginPassword === '') {
    echo json_encode(['ok' => false, 'msg' => '비밀번호가 비어 있습니다.']);
    exit;
}

$db  = (new DBConnection())->getDB();
$sql = "SELECT password
        FROM users
        WHERE user_no = :user_no
        LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->bindValue(':user_no', (int)$_SESSION['user_no'], PDO::PARAM_INT);
$stmt->execute();
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow || empty($userRow['password']) ||
    !password_verify($loginPassword, $userRow['password'])) {
    echo json_encode(['ok' => false, 'msg' => '로그인 비밀번호가 일치하지 않습니다.']);
    exit;
}

echo json_encode(['ok' => true]);
exit;
