<?php

// 1. 공통 로더 (env, DB, Redis 등 초기화)
require_once __DIR__ . '/../../../connection/loader.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 로그인 Model 클래스 (옛날 password_connection 상속 ❌)
class password_0_login_Model_Module
{
    /** @var PDO */
    protected $db;

    public function __construct()
    {
        // DB 연결 객체 생성
        $this->db = (new DBConnection())->getDB();
        // 필요하면 Redis 도 여기서 생성 가능:
        // $this->redis = (new RedisConnection())->getRedis();
    }

    /**
     * 관리자 로그인 처리 (users 테이블 사용)
     * @param string $userid   - 로그인 아이디
     * @param string $password - 로그인 비밀번호(평문 입력)
     * @return bool
     */
    public function adminLogin($userid, $password)
    {
        // users 테이블 조회
        $sql = "SELECT * FROM users WHERE userid = :userid LIMIT 1";
        $stmt = $this->db->prepare($sql);   // ← $this->connection → $this->db 로 변경
        $stmt->bindParam(":userid", $userid, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();

        if ($user) {

            // 1) bcrypt 비밀번호(정상 케이스)
            if (password_verify($password, $user['password'])) {

                // 세션값 저장
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['userid']    = $user['userid'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];

                return true;
            }

            // 2) 혹시 평문 비번일 경우 대비
            if ($user['password'] === $password) {

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['userid']    = $user['userid'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];

                return true;
            }
        }

        // 로그인 실패
        return false;
    }
}

// 클래스 객체 생성 (기존처럼 유지)
$password_0_login_Model_Module = new password_0_login_Model_Module();
