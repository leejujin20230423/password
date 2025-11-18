<?php
session_start();
if(isset($_SESSION['username'])){
    header("Location: view.php"); // 로그인 되어 있으면 등록 화면으로
    exit;
}
?>

<h2>비밀번호 관리</h2>
<a href="login.php">로그인</a> | 
<a href="register.php">회원가입</a>
