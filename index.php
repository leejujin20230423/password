<?php
session_start();

// 로그인 안 되어 있으면 로그인 페이지로 이동
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<h2>등록 화면</h2>
<p>안녕하세요, <?php echo htmlspecialchars($_SESSION['username']); ?>님!</p>

<a href="logout.php">로그아웃</a>
