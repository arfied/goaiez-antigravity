<?php
require __DIR__.'/app/vendor/autoload.php';
$app = require_once __DIR__.'/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$job = \Illuminate\Support\Facades\DB::table('jobs')->first();
echo "Job ID: " . $job->id . "\n";
echo "Payload: " . $job->payload . "\n";
