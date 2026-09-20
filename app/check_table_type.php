<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.database' => 'goaiez_antig_test']);
config(['database.default' => 'pgsql']);

$type = Illuminate\Support\Facades\DB::select("SELECT table_type FROM information_schema.tables WHERE table_name='agencies'");
dump($type);
