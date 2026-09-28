<?php

declare(strict_types=1);

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('This router requires the isolated in-memory login test environment.');
}

$root = dirname(__DIR__, 2);
$public = realpath($root.'/public');
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath($public.$uri);
if ($uri !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

define('LARAVEL_START', microtime(true));
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
// Test the production build without moving or changing the developer's Vite hot file.
$app->afterResolving(Vite::class, static function (Vite $vite) use ($root): void {
    $vite->useHotFile($root.'/storage/framework/testing/demo-login-qa/unused-hot');
});
$app->handleRequest(Request::capture());
