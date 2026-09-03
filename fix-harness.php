<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
        \$msg = \Illuminate\Support\Facades\DB::table('outreach_messages')->where('status', '!=', 'queued')->latest('id')->first();
        return \$msg ? (array) \$msg : null;
PHP;
$replacement = <<<PHP
        \$msg = \Illuminate\Support\Facades\DB::table('outreach_messages')->where('status', '!=', 'queued')->latest('id')->first();
        if (\$msg) {
            \$arr = (array) \$msg;
            \$arr['provider_message_id'] = \$arr['provider_msg_id'] ?? null;
            return \$arr;
        }
        return null;
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
