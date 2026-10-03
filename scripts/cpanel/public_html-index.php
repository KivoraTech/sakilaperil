<?php

/*
 * cPanel front controller for the layout where the app lives NEXT TO public_html:
 *   /home/<cpaneluser>/sakila/        ← the Laravel app (outside the web root: .env, code, logs)
 *   /home/<cpaneluser>/public_html/   ← this file + build/, logo.png … (the only public folder)
 * If your app folder has another name, change SAKILA_APP below.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

const SAKILA_APP = __DIR__.'/../sakila';

if (file_exists($maintenance = SAKILA_APP.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require SAKILA_APP.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once SAKILA_APP.'/bootstrap/app.php';

// public_path() → this folder (Vite manifest in ./build, logo.png, icon.png …).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
