<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.database' => 'goaiez_antig_test']);
config(['database.default' => 'pgsql']);

dump("Testing DB tables:");
$tables = Illuminate\Support\Facades\DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
$hasAgencies = false;
foreach($tables as $t) {
    if ($t->table_name === 'agencies') $hasAgencies = true;
}
dump("Has agencies: " . ($hasAgencies ? "YES" : "NO"));
