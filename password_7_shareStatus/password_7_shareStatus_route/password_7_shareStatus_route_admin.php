<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use PassApp\Auth\AuthGate;

(new AuthGate())->requireLogin();

require_once dirname(__DIR__) . '/password_7_shareStatus_view/password_7_shareStatus_view_admin/password_7_shareStatus_view_admin.php';

