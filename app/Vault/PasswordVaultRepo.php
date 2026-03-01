<?php
declare(strict_types=1);

namespace PassApp\Vault;

use PassApp\Core\DbHub;
use PassApp\Core\CacheHub;
use PDO;

final class PasswordVaultRepo
{
    private PDO $pdo;
    private ?\Redis $redis;

    public function __construct(?PDO $pdo=null, ?\Redis $redis=null)
    {
        $this->pdo = $pdo ?? DbHub::pdo();
        $this->redis = $redis ?? CacheHub::redis();
    }

    public function listForUser(int $userNo): array
    {
        $cacheKey = "vault:list:user:{$userNo}";
        if ($this->redis) {
            $cached = $this->redis->get($cacheKey);
            if (is_string($cached)) {
                $data = json_decode($cached, true);
                if (is_array($data)) return $data;
            }
        }

        $sql = 'SELECT * FROM password WHERE user_no_Fk = :uno ORDER BY password_idno DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uno' => $userNo]);
        $rows = $stmt->fetchAll() ?: [];

        if ($this->redis) {
            $this->redis->setex($cacheKey, 30, json_encode($rows, JSON_UNESCAPED_UNICODE));
        }
        return $rows;
    }

    public function create(int $userNo, array $payload): int
    {
        $sql = 'INSERT INTO password (user_no_Fk, password_title, password_url, password_userid, password_pass, password_memo)
                VALUES (:uno, :title, :url, :uid, :pw, :memo)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uno' => $userNo,
            ':title' => (string)($payload['title'] ?? ''),
            ':url' => (string)($payload['url'] ?? ''),
            ':uid' => (string)($payload['userid'] ?? ''),
            ':pw' => (string)($payload['pass'] ?? ''),
            ':memo' => (string)($payload['memo'] ?? ''),
        ]);

        CacheHub::forgetByPrefix("vault:list:user:{$userNo}");
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $userNo, int $id, array $payload): void
    {
        $sql = 'UPDATE password
                SET password_title=:title, password_url=:url, password_userid=:uid, password_pass=:pw, password_memo=:memo
                WHERE password_idno=:id AND user_no_Fk=:uno';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title' => (string)($payload['title'] ?? ''),
            ':url' => (string)($payload['url'] ?? ''),
            ':uid' => (string)($payload['userid'] ?? ''),
            ':pw' => (string)($payload['pass'] ?? ''),
            ':memo' => (string)($payload['memo'] ?? ''),
            ':id' => $id,
            ':uno' => $userNo,
        ]);

        CacheHub::forgetByPrefix("vault:list:user:{$userNo}");
    }

    public function delete(int $userNo, int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $delShare = $this->pdo->prepare('DELETE FROM password_share WHERE password_idno_Fk = :id');
            $delShare->execute([':id' => $id]);

            $del = $this->pdo->prepare('DELETE FROM password WHERE password_idno = :id AND user_no_Fk = :uno');
            $del->execute([':id' => $id, ':uno' => $userNo]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        CacheHub::forgetByPrefix("vault:list:user:{$userNo}");
    }
}
