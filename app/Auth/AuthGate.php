<?php
declare(strict_types=1);

namespace PassApp\Auth;

use PassApp\Core\DbHub;
use PassApp\Core\SessionVault;
use PassApp\Security\SecretHasher;
use PDO;
use Throwable;

final class AuthGate
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbHub::pdo();
    }

    public function attempt(string $userId, string $plainPw): bool
    {
        $userId = trim($userId);
        if ($userId === '' || $plainPw === '') {
            return false;
        }

        // ✅ DB 스키마에 맞춤: userid / username / password / user_type / status
        $sql = <<<SQL
SELECT
  user_no,
  userid,
  username,
  password,
  user_type,
  status
FROM users
WHERE userid = :userid
LIMIT 1
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // ✅ 500으로 죽지 않게 로그만 남기고 실패 처리
            error_log('[AuthGate] DB error: ' . $e->getMessage());
            return false;
        }

        if (!$row) return false;

        $stored = (string)($row['password'] ?? '');
        if ($stored === '') return false;

        // (선택) status가 비활성이면 로그인 막기
        // status 값 정책이 불명확해서 "0/NULL이면 허용"으로 두고,
        // 1이 활성 같은 정책이면 아래를 조정하세요.
        // if (isset($row['status']) && (int)$row['status'] === 0) return false;

        // ✅ 해시 검증 + (레거시) 평문 저장 시 성공하면 해시로 업그레이드
        $isHashedOk = SecretHasher::verify($plainPw, $stored);
        $isPlainOk  = hash_equals($stored, $plainPw);

        if (!$isHashedOk && !$isPlainOk) return false;

        // 평문이었다면 해시로 업그레이드
        if (!$isHashedOk && $isPlainOk) {
            try {
                $upgrade = $this->pdo->prepare(
                    'UPDATE users SET password = :hpw, updated_at = CURRENT_TIMESTAMP WHERE user_no = :uno'
                );
                $upgrade->execute([
                    ':hpw' => SecretHasher::hash($plainPw),
                    ':uno' => (int)$row['user_no'],
                ]);
            } catch (Throwable $e) {
                // 업그레이드 실패는 로그인 성공을 막을 정도는 아니니 로그만
                error_log('[AuthGate] password upgrade failed: ' . $e->getMessage());
            }
        }

        // ✅ 세션 키: 기존 화면 호환 고려
        SessionVault::put('user_no', (int)$row['user_no']);
        SessionVault::put('userid', (string)($row['userid'] ?? $userId));
        SessionVault::put('user_name', (string)($row['username'] ?? '')); // 기존 user_name 호환
        SessionVault::put('username', (string)($row['username'] ?? ''));  // 레거시 username 호환
        SessionVault::put('user_type', (string)($row['user_type'] ?? ''));
        SessionVault::put('user_level', (string)($row['user_type'] ?? '')); // user_level 컬럼이 없어서 user_type로 매핑
        SessionVault::put('is_logged_in', true);

        // ⚠️ 예전 코드에서 id를 기대하는 화면이 있으면 "user_no"로 호환 제공
        // (DB에 id 컬럼이 없으므로 user_no를 넣어줌)
        SessionVault::put('id', (int)$row['user_no']);

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
