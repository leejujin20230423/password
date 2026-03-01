<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use PassApp\Core\SessionVault;
use PassApp\Controller\VaultController;
use PassApp\Auth\AuthGate;

(new AuthGate())->requireLogin();

$userType = (string)SessionVault::pull('user_type', '');
$ctl = new VaultController();

if ($userType === 'admin' || $userType === 'master') {
    $ctl->indexAdmin();
} else {
    $ctl->indexUser();
}
