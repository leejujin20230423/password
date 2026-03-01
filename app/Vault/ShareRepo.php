<?php
declare(strict_types=1);

namespace PassApp\Vault;

use PassApp\Core\DbHub;
use PDO;

final class ShareRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo=null)
    {
        $this->pdo = $pdo ?? DbHub::pdo();
    }

    public function searchUser(string $keyword): array
    {
        $kw = '%' . $keyword . '%';
        $stmt = $this->pdo->prepare('SELECT user_no, userid, user_name, user_level FROM users WHERE userid LIKE :kw OR user_name LIKE :kw ORDER BY user_no DESC LIMIT 30');
        $stmt->execute([':kw' => $kw]);
        return $stmt->fetchAll() ?: [];
    }

    public function grant(int $passwordId, int $targetUserNo, int $byUserNo): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO password_share (password_idno_Fk, user_no_Fk, granted_by_user_no, created_at) VALUES (:pid, :uno, :by, NOW())');
        $stmt->execute([':pid'=>$passwordId, ':uno'=>$targetUserNo, ':by'=>$byUserNo]);
    }

    public function revoke(int $shareId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_share WHERE password_share_idno = :sid');
        $stmt->execute([':sid'=>$shareId]);
    }

    public function listSharesForPassword(int $passwordId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT ps.password_share_idno, ps.user_no_Fk, u.userid, u.user_name, ps.created_at
            FROM password_share ps
            JOIN users u ON u.user_no = ps.user_no_Fk
            WHERE ps.password_idno_Fk = :pid
            ORDER BY ps.password_share_idno DESC
        ');
        $stmt->execute([':pid'=>$passwordId]);
        return $stmt->fetchAll() ?: [];
    }
}
