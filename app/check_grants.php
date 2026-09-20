<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.database' => 'goaiez_antig_test']);
config(['database.default' => 'pgsql']);

dump("Testing DB grants for agencies:");
$grants = Illuminate\Support\Facades\DB::select("SELECT grantee, privilege_type FROM information_schema.role_table_grants WHERE table_name='agencies'");
dump($grants);
