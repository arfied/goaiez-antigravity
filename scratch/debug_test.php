<?php

require __DIR__ . '/app/vendor/autoload.php';
$app = require_once __DIR__ . '/app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);
Auth::login($user);

$response = app('router')->prepareResponse(request(), app('router')->dispatch(Illuminate\Http\Request::create('/admin/x-10/routing-rules')));
echo $response->getStatusCode() . "\n";
if ($response->getStatusCode() == 302) {
    echo $response->headers->get('Location') . "\n";
}
