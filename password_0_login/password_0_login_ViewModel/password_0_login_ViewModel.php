<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * 현재 페이지가 로그인 페이지일 때만
 * 이미 로그인된 사용자를 index.php로 보내야 한다.
 *
 * 즉, login_view가 아닌 다른 페이지에서는 실행되면 안됨.
 */

$currentFile = basename($_SERVER['PHP_SELF']);

if ($currentFile === 'password_0_login_View.php') {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        header("Location:");
        exit;
    }
}

?>
