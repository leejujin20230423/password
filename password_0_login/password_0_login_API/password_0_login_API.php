<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use PassApp\Controller\LoginController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /password_0_login/password_0_login_Route/password_0_login_route.php');
    exit;
}

(new LoginController())->submit();
