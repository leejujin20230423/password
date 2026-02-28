<?php

// 1) 공통 로더 (env, DB, Redis 등 초기화) - 기존 경로 유지
require_once dirname(__DIR__, 2) . '/app_bootstrap.php';
pass_require_loader_or_die();

// 세션은 "필요할 때" 시작하는 쪽이 안전하지만,
// 기존 구조에서 세션 의존도가 높아 여기서는 유지합니다.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 회원가입 완료 후 돌아올 때 사용할 메시지 (현재 파일 내부에서만 사용)
$registerMessageForLoginView = '';
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $registerMessageForLoginView = '회원가입이 완료되었습니다. 가입한 아이디로 로그인해주세요.';
}

/**
 * 로그인 Model 클래스 (users 테이블 기반)
 * - 기존 클래스명/인스턴스명은 외부 의존 때문에 유지
 */
class password_0_login_Model_Module
{
    /** @var PDO */
    protected $pdoConnection;

    public function __construct()
    {
        $this->pdoConnection = (new DBConnection())->getDB();
    }

    /**
     * ✅ (신규) 사용자 정보 조회
     * @return array<string,mixed>|null
     */
    private function fetchUserRowByUserId(string $userId): ?array
    {
        $selectUserSql = 'SELECT * FROM users WHERE userid = :userid LIMIT 1';
        $selectUserStmt = $this->pdoConnection->prepare($selectUserSql);
        $selectUserStmt->bindValue(':userid', $userId, PDO::PARAM_STR);
        $selectUserStmt->execute();

        $userRow = $selectUserStmt->fetch(PDO::FETCH_ASSOC);
        return $userRow ?: null;
    }

    /**
     * ✅ (신규) 세션에 로그인 정보 저장
     */
    private function persistLoginSession(array $userRow): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 핵심 세션 값
        $_SESSION['user_no']   = $userRow['user_no'];
        $_SESSION['userid']    = $userRow['userid'];
        $_SESSION['username']  = $userRow['username'];
        $_SESSION['user_type'] = $userRow['user_type'];

        // index.php 등에서 사용하는 공통 플래그 (없을 수도 있어 방어적으로 세팅)
        $_SESSION['is_logged_in'] = true;
    }

    /**
     * 관리자 로그인 처리 (users 테이블 사용)
     * - 기존 메서드명은 그대로 유지 (외부 호출 호환)
     */
    public function adminLogin($userid, $password): bool
    {
        $normalizedUserId = trim((string)$userid);
        $plainPasswordInput = (string)$password;

        if ($normalizedUserId === '' || $plainPasswordInput === '') {
            return false;
        }

        $userRow = $this->fetchUserRowByUserId($normalizedUserId);
        if (!$userRow) {
            return false;
        }

        $hashedPasswordInDb = (string)($userRow['password'] ?? '');

        // 1) bcrypt 비밀번호(정상 케이스)
        $isBcryptMatch = ($hashedPasswordInDb !== '') && password_verify($plainPasswordInput, $hashedPasswordInDb);
        if ($isBcryptMatch) {
            $this->persistLoginSession($userRow);
            return true;
        }

        // 2) 혹시 평문 비번일 경우 대비
        $isPlainMatch = ($hashedPasswordInDb !== '') && hash_equals($hashedPasswordInDb, $plainPasswordInput);
        if ($isPlainMatch) {
            $this->persistLoginSession($userRow);
            return true;
        }

        return false;
    }
}

// 클래스 객체 생성 (기존처럼 유지)
$password_0_login_Model_Module = new password_0_login_Model_Module();
