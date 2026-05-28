<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Env;

session_start();

require_once __DIR__ . '/../app/Core/Autoloader.php';

Autoloader::register();
Env::load(dirname(__DIR__) . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

$app = new App();
$app->run();
