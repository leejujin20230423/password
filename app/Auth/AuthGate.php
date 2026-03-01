<?php
declare(strict_types=1);

namespace PassApp\Auth;

use PassApp\Core\DbHub;
use PassApp\Core\SessionVault;
use PassApp\Security\SecretHasher;
use PDO;

final class AuthGate
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbHub::pdo();
    }

    public function attempt(string $userId, string $password): bool
    {
        $sql = 'SELECT user_no, id, userid, user_name, user_level, user_type, user_password FROM users WHERE userid = :userid LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row) return false;

        $hash = (string)($row['user_password'] ?? '');
        if ($hash === '') return false;

        // Backward-compatible: if stored as plaintext, upgrade on success.
        $ok = SecretHasher::verify($password, $hash) || hash_equals($hash, $password);
        if (!$ok) return false;

        if (!SecretHasher::verify($password, $hash)) {
            $upgrade = $this->pdo->prepare('UPDATE users SET user_password = :hpw WHERE user_no = :uno');
            $upgrade->execute([
                ':hpw' => SecretHasher::hash($password),
                ':uno' => (int)$row['user_no'],
            ]);
        }

                SessionVault::put('user_no', (int)$row['user_no']);
        // compatibility: some screens use id instead of user_no
        if (isset($row['id'])) {
            SessionVault::put('id', (int)$row['id']);
        }
        SessionVault::put('userid', (string)($row['userid'] ?? $userId));
        SessionVault::put('user_name', (string)($row['user_name'] ?? ''));
        SessionVault::put('user_level', (string)($row['user_level'] ?? ''));
        SessionVault::put('user_type', (string)($row['user_type'] ?? (string)($row['user_level'] ?? '')));
        SessionVault::put('is_logged_in', true);

        return true;
    }

    public function requireLogin(): void
    {
        if (!SessionVault::isLoggedIn()) {
            header('Location: /password_0_login/password_0_login_Route/password_0_login_route.php');
            exit;
        }
    }
}
