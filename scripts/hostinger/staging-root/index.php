<?php

// Front controller for staging.ampuh.finance. The app lives next to this file in web_umkm/
// (same layout as production); scripts/deploy-hostinger.sh staging uploads this file.

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

if (file_exists($maintenance = __DIR__.'/web_umkm/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/web_umkm/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/web_umkm/bootstrap/app.php';

$app->handleRequest(Request::capture());
