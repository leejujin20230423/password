<?php
require_once "db.php";

$db = new Database();
$pdo = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'];
    $password = $_POST['password'];

    // 비밀번호 해시 생성
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // DB에 저장
    $sql = "INSERT INTO pass_users (username, password_hash) VALUES (:username, :password_hash)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':password_hash' => $password_hash
    ]);

    echo "회원가입 완료!";
}
?>

<form method="POST">
    <p>아이디: <input type="text" name="username" required></p>
    <p>비밀번호: <input type="password" name="password" required></p>
    <button type="submit">회원가입</button>
</form>
