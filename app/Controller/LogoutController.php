<?php
declare(strict_types=1);

namespace PassApp\Controller;

use PassApp\Core\SessionVault;

final class LogoutController
{
    public function run(): void
    {
        SessionVault::nuke();
        header('Location: /password_0_login/password_0_login_Route/password_0_login_route.php');
        exit;
    }
}
