<?php
// 🔹 DB 연결 클래스
class Database {
    private $host = "49.247.29.76";      // DB 서버 주소
    private $db_name = "password";    // DB 이름(이걸 쓰는 게 맞다면 그대로 사용)
    private $username = "lokia";         // DB 계정
    private $password = "lokia0528**";   // DB 비밀번호
    public $conn;

    public function connect() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password);

            // PDO 에러 모드 설정
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            echo "DB 연결 실패: " . $e->getMessage();
            exit;
        }

        return $this->conn;
    }
}
?>
