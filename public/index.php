<?php

declare(strict_types=1);

ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');
ini_set('log_errors', '1');
ini_set('error_reporting', (string)E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Response;

set_exception_handler(function (Throwable $e) {
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::internalError('Internal server error');
});

set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$app = App::init();
$app->run();
