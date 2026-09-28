<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\DemoManagementFixtures;

$root = dirname(__DIR__, 2);
$expected = str_replace('\\', '/', $root.'/storage/framework/testing/demo-management-browser.sqlite');
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || str_replace('\\', '/', getenv('DB_DATABASE') ?: '') !== $expected) {
    throw new RuntimeException('Browser preparation requires the dedicated testing database.');
}
require $root.'/vendor/autoload.php';
if (! is_dir(dirname($expected))) mkdir(dirname($expected), 0777, true);
if (! file_exists($expected)) touch($expected);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== $expected) {
    throw new RuntimeException('Unexpected resolved database configuration.');
}
Artisan::call('migrate', ['--force' => true]);
if (\App\Models\User::exists()) throw new RuntimeException('Test users already exist. Refusing to overwrite them.');
$fixtures = DemoManagementFixtures::create();
$result = [];
foreach ($fixtures['parks'] as $key => $park) {
    foreach (['parking','reservation','customer','subscription','stay','invoice','apiPlatform'] as $model) $result[$key][$model] = $park[$model]->id;
    $result[$key]['public_key'] = $park['parking']->public_booking_key;
}
file_put_contents(dirname($expected).'/demo-management-fixtures.json', json_encode($result, JSON_PRETTY_PRINT));
echo "Isolated demo browser fixtures ready.\n";
