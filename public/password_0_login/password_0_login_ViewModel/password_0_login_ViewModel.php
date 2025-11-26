<?php
// 1. SessionManager 불러오기
require_once __DIR__ . '/../../../SessionManager/SessionManager.php';

// 2. 세션 시작 (세션 폴더 지정 + session_start까지 내부에서 처리)
SessionManager::start();

/**
 * 현재 페이지가 로그인 페이지일 때만
 * 이미 로그인된 사용자를 index.php로 보내야 한다.
 *
 * 즉, login_view가 아닌 다른 페이지에서는 실행되면 안됨.
 */

// 현재 실행 중인 파일 이름
$currentFile = basename($_SERVER['PHP_SELF']);

// 이 파일이 로그인 화면일 때만 체크
if ($currentFile === 'password_0_login_View.php') {

    // SessionManager를 통해 로그인 여부 검사
    if (SessionManager::isLoggedIn()) {
        // 이미 로그인된 상태이면 메인으로 보냄
        // ※ 필요에 따라 이동 경로는 변경해서 사용해도 됨
        header("Location: /index.php");
        exit;
    }
}


// --------------------------------------------------------
// password_login_0_route_session viewmodel
//   - 세션에 들어있는 로그인 정보를 이용해서
//     화면에 보여줄 문자열을 만들어주는 역할
// --------------------------------------------------------
class password_login_0_route_session_viewmodel_Module
{
    public function __construct()
    {
        // 생성자 (필요 시 초기화 코드 작성)
    }

    /**
     * 로그인 여부 + user_no 를 문자열로 리턴
     */
    public function password_login_0_route_session_viewmodel()
    {
        if (SessionManager::isLoggedIn()) {
            $userId = SessionManager::getUserId();  // 세션에서 user_no 가져오기
            return "로그인된 사용자 ID: " . $userId;
        } else {
            return "사용자가 로그인하지 않았습니다.";
        }
    }

    /**
     * 예: "사용자 매장 근무시간 합계" 등의 ViewModel 용도
     *  - 지금은 예제로 user_no만 리턴하지만,
     *    나중에 근무시간 합계를 계산해서 여기서 같이 리턴해도 됨.
     */
    public function password_login_0_route_session_viewmodel_사용자매장근무시간합계()
    {
        if (SessionManager::isLoggedIn()) {
            $userId = SessionManager::getUserId();
            return "로그인된 사용자 ID: " . $userId;
        } else {
            return "사용자가 로그인하지 않았습니다.";
        }
    }
}

/**
 * (전역 함수 버전)
 *  - 기존에 이 함수 이름으로 많이 써놨다면,
 *    그대로 유지하면서 내부에서 SessionManager를 쓰게 변경
 */
function password_login_0_route_session_viewmodel()
{
    if (SessionManager::isLoggedIn()) {
        $userId = SessionManager::getUserId();
        return "로그인된 사용자 ID: " . $userId;
    } else {
        return "사용자가 로그인하지 않았습니다.";
    }
}
