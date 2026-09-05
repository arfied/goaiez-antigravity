<?php
require __DIR__.'/../app/vendor/autoload.php';
$app = require_once __DIR__.'/../app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$route = Illuminate\Support\Facades\Route::get('/test-lw-layout', \App\Modules\X198\Ui\ConnectCard::class);
echo method_exists($route, 'layout') ? "Yes" : "No";
