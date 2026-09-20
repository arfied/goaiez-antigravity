<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.pgsql.database' => 'goaiez_antig_test']);
config(['database.default' => 'pgsql']);
Illuminate\Support\Facades\DB::purge('pgsql');

$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
dump($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS));
dump($pdo->query("SELECT current_database()")->fetchColumn());
dump($pdo->query("SELECT current_user")->fetchColumn());
dump($pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'agencies%'")->fetchAll(PDO::FETCH_COLUMN));
