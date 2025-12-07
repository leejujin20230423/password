<?php

echo "여기는 password_9_logout_view_master.php 파일입니다.";
exit;
session_start();
session_destroy();
header("Location: index.php");
exit;
