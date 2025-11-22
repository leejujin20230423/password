<?php

class DBConnection
{
    protected $db;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';

        try {
            $this->db = new PDO(
                "mysql:host={$config['host']};dbname={$config['name']}",
                $config['user'],
                $config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die("MySQL 접속 실패: " . $e->getMessage());
        }
    }

    public function getDB()
    {
        return $this->db;
    }
}
