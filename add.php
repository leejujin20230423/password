<?php
require_once "db.php";
require_once "crypto.php";
require_once "auth_check.php";


// DB 연결
$db = new Database();
$pdo = $db->connect();

// 암호화 객체
$crypto = new PasswordCrypto();

// 폼 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category   = $_POST['category'];
    $site_url   = $_POST['site_url'];
    $login_id   = $_POST['login_id'];
    $password   = $_POST['password'];
    $memo       = $_POST['memo'];

    // 🔒 비밀번호 암호화
    $encrypted_password = $crypto->encrypt($password);

    // DB 저장
    $sql = "INSERT INTO password (category, site_url, login_id, encrypted_password, memo)
            VALUES (:category, :site_url, :login_id, :encrypted_password, :memo)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':category' => $category,
        ':site_url' => $site_url,
        ':login_id' => $login_id,
        ':encrypted_password' => $encrypted_password,
        ':memo' => $memo
    ]);

    // 저장 후 목록으로 이동
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>비밀번호 등록</title>
</head>
<body>

<h2>비밀번호 등록</h2>

<form method="POST">
    <p>구분: <input type="text" name="category" required></p>
    <p>사이트 주소: <input type="text" name="site_url" required></p>
    <p>아이디: <input type="text" name="login_id" required></p>
    <p>비밀번호: <input type="text" name="password" required></p>
    <p>메모: <textarea name="memo"></textarea></p>

    <button type="submit">등록하기</button>
</form>

<br>
<a href="index.php">← 목록으로</a>

</body>
</html>
