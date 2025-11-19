<?php

// require_once __DIR__ . "/auth_check.php";  // 로그인 체크


class password_connection
{
    protected $connection; // 데이터베이스 연결을 저장할 protected 변수

    public function __construct()
    {
        $this->initializeConnection();
    }

    public function initializeConnection()
    {
        try {
            $this->connection = new PDO(
                'mysql:host=49.247.29.76;dbname=password', 
                'lokia', 
                'lokia0528**',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // 예외 모드 설정
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // 기본 페치 모드 설정
                ]
            );
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }

    public function handleConnectionError(PDOException $e)
    {
        die("MySQL 접속 실패: " . $e->getMessage());
    }

    public function getConnection()
    {
        return $this->connection;
    }
}

?>

