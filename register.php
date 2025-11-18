<?php
require_once "db.php";

$db = new Database();
$pdo = $db->connect();

$error = "";
$success = "";

// 회원가입 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 기존 계정 확인
    $stmt = $pdo->prepare("SELECT * FROM pass_users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        $error = "이미 존재하는 아이디입니다. 비밀번호를 갱신하려면 관리자에게 문의하세요.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO pass_users (username, password_hash) VALUES (:username, :password_hash)");
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $password_hash
        ]);
        $success = "회원가입이 완료되었습니다. 이제 로그인하세요.";
    }
}
?>

<h2>회원가입</h2>
<form method="POST">
    <p>아이디: <input type="text" name="username" required></p>
    <p>비밀번호: <input type="password" name="password" required></p>
    <button type="submit">회원가입</button>
</form>

<?php if($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<?php if($success): ?>
    <p style="color:green;"><?php echo $success; ?></p>
<?php endif; ?>

<a href="login.php">로그인</a>
