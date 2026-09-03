<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
        \Illuminate\Support\Facades\Http::fake([
            "*/calls/1/calls/{\$callId}" => \Illuminate\Support\Facades\Http::response([
                'id' => \$callId,
                'from' => \$from,
                'to' => env('INFOBIP_SENDER', '+19015922708'),
PHP;
$replacement = <<<PHP
        \$to = env('INFOBIP_SENDER', '+19015922708');
        // If the business has a number, use it!
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('business_id', \$tenant['id'])->first();
        if (\$pool) {
            \$to = \$pool->phone_number;
        }

        \Illuminate\Support\Facades\Http::fake([
            "*/calls/1/calls/{\$callId}" => \Illuminate\Support\Facades\Http::response([
                'id' => \$callId,
                'from' => \$from,
                'to' => \$to,
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
