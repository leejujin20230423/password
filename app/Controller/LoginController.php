<?php
declare(strict_types=1);

namespace PassApp\Controller;

use PassApp\Auth\AuthGate;

final class LoginController
{
    public function show(): void
    {
        require __DIR__ . '/../../password_0_login/password_0_login_View/password_0_login_View.php';
    }

    public function submit(): void
    {
        $userId = trim((string)($_POST['password_admin_userid'] ?? ''));
        $pass   = (string)($_POST['password_admin_pass'] ?? '');

        $gate = new AuthGate();
        if ($gate->attempt($userId, $pass)) {
            header('Location: /index.php');
            exit;
        }

        header('Location: /password_0_login/password_0_login_Route/password_0_login_route.php?error=1');
        exit;
    }
}
