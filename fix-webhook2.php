<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
        \$state = 'NO_ANSWER';
        if (\$event === 'call.answered') {
            \$state = 'ESTABLISHED'; // Or whatever Infobip calls it.
        }
        
        \Illuminate\Support\Facades\Http::fake([
            "*/calls/1/calls/{\$callId}" => \Illuminate\Support\Facades\Http::response([
                'id' => \$callId,
                'from' => \$from,
                'to' => env('INFOBIP_SENDER', '+19015922708'),
                'direction' => 'INBOUND',
                'state' => \$state,
                'startTime' => now()->subSeconds(10)->toIso8601String(),
                'answerTime' => \$state === 'ESTABLISHED' ? now()->subSeconds(5)->toIso8601String() : null,
PHP;
$replacement = <<<PHP
        \$state = 'NO_ANSWER';
        if (\$event === 'call.answered') {
            \$state = 'FINISHED';
        }
        
        \Illuminate\Support\Facades\Http::fake([
            "*/calls/1/calls/{\$callId}" => \Illuminate\Support\Facades\Http::response([
                'id' => \$callId,
                'from' => \$from,
                'to' => env('INFOBIP_SENDER', '+19015922708'),
                'direction' => 'INBOUND',
                'state' => \$state,
                'startTime' => now()->subSeconds(10)->toIso8601String(),
                'answerTime' => \$state === 'FINISHED' ? now()->subSeconds(5)->toIso8601String() : null,
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
