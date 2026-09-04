<?php

use Illuminate\Contracts\Http\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// I can't easily run it this way. I will just run pest with a dump.
