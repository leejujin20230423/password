<?php
session_start();
if(isset($_SESSION['username'])){
    echo "안녕하세요, ".$_SESSION['username']."님! <a href='logout.php'>로그아웃</a>";
    exit;
}
?>

<h2>비밀번호 관리</h2>

<a href="login.php">로그인</a> | 
<a href="register.php">회원가입</a>
