<?php
session_start();
require_once "db.php";

$db = new Database();
$pdo = $db->connect();

$error = "";

// 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM pass_users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
        exit;
    } else {
        $error = "아이디 또는 비밀번호가 잘못되었습니다.";
    }
}
?>

<h2>로그인</h2>
<form method="POST">
    <p>아이디: <input type="text" name="username" required></p>
    <p>비밀번호: <input type="password" name="password" required></p>
    <button type="submit">로그인</button>
</form>

<?php if($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>
<a href="register.php">회원가입</a>
