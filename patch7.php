<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search1 = <<<PHP
        DB::table('conversations')
            ->where('business_id', \$tenant['id'])
            ->update(['agent_status' => 'agent_handling', 'agent_turns_used' => 0]);
PHP;
$replace1 = <<<PHP
        \$customer = DB::table('customers')->where('business_id', \$tenant['id'])->where('phone', \$customerPhone)->first();
        DB::table('conversations')->insertOrIgnore([
            'business_id' => \$tenant['id'],
            'customer_id' => \$customer->id,
            'channel' => 'sms',
            'status' => 'new',
            'agent_status' => 'agent_handling',
            'agent_turns_used' => 0,
            'is_bot_handled' => true,
            'consent_logged_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
PHP;
$content = str_replace($search1, $replace1, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
