<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use PassApp\Controller\ShareController;

(new ShareController())->ajaxAdmin();
