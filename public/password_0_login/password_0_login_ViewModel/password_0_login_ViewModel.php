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



// <!-- password_login_0_route_session viewmodel
class password_login_0_route_session_viewmodel_Module
{
    public function __construct()
    {
        // 생성자
    }


    // login 정보 세션 가져오기 viewmodel
    function password_login_0_route_session_viewmodel() {
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            return "로그인된 사용자 ID: " . $_SESSION['user_id'];
        } else {
            return "사용자가 로그인하지 않았습니다.";
        }
    }

    function password_login_0_route_session_viewmodel_사용자매장근무시간합계() {
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            return "로그인된 사용자 ID: " . $_SESSION['user_id'];
        } else {
            return "사용자가 로그인하지 않았습니다.";
        }
    }
}
function password_login_0_route_session_viewmodel() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return "로그인된 사용자 ID: " . $_SESSION['user_id'];
    } else {
        return "사용자가 로그인하지 않았습니다.";
    }
}

?>
