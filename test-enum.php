<?php
require __DIR__.'/app/vendor/autoload.php';
$app = require_once __DIR__.'/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$record = new \App\Models\ConsentRecord();
$record->channel = \App\Enums\OutreachChannel::Sms->value;
var_dump($record->toArray());
