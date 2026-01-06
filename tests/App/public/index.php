<?php

declare(strict_types=1);

use Ecourty\SitemapBundle\Tests\App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] ?? 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '1';

if ($_SERVER['APP_DEBUG']) {
    \umask(0000);

    Debug::enable();
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
