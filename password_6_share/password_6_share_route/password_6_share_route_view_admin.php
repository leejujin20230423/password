<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use PassApp\Auth\AuthGate;

(new AuthGate())->requireLogin();

require_once dirname(__DIR__) . '/password_6_share_view/password_6_share_view_admin/password_6_share_view_admin.php';

