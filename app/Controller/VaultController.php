<?php
declare(strict_types=1);

namespace PassApp\Controller;

use PassApp\Auth\AuthGate;
use PassApp\Core\SessionVault;
use PassApp\Security\CsrfShield;
use PassApp\Vault\PasswordVaultRepo;

final class VaultController
{
    public function indexAdmin(): void
    {
        (new AuthGate())->requireLogin();
        require __DIR__ . '/../../password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_admin/password_5_passwordRegister_View_admin.php';
    }

    public function indexUser(): void
    {
        (new AuthGate())->requireLogin();
        require __DIR__ . '/../../password_5_passwordRegister/password_5_passwordRegister_View/password_5_passwordRegister_View_user/password_5_passwordRegister_View_user.php';
    }

    public function apiDelete(): void
    {
        (new AuthGate())->requireLogin();
        CsrfShield::assert($_POST['csrf_token'] ?? null);

        $userNo = (int)SessionVault::pull('user_no', 0);
        $id = (int)($_POST['password_idno'] ?? 0);
        if ($userNo <= 0 || $id <= 0) {
            http_response_code(400);
            echo '잘못된 요청';
            exit;
        }

        $repo = new PasswordVaultRepo();
        $repo->delete($userNo, $id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
