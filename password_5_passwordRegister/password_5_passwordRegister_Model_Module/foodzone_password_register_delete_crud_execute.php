<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use PassApp\Controller\VaultController;

(new VaultController())->apiDelete();
