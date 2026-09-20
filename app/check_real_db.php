<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.database' => 'goaiez_antig_test']);
config(['database.default' => 'pgsql']);
Illuminate\Support\Facades\DB::purge('pgsql');

dump(Illuminate\Support\Facades\DB::select("SELECT current_database()"));
