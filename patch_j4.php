<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function personWithPendingSteps(array \$tenant, int \$count): array
    {
        throw \$this->todo('a person with N campaign steps ALREADY QUEUED — the STOP test needs in-flight work');
    }
PHP;
$replace = <<<PHP
    private function personWithPendingSteps(array \$tenant, int \$count): array
    {
        \$phone = '+1555000' . rand(1000, 9999);
        \$customerId = \Illuminate\Support\Facades\DB::table('customers')->insertGetId([
            'business_id' => \$tenant['id'],
            'location_id' => \$tenant['location_id'],
            'phone' => \$phone,
            'channel' => 'sms',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        for (\$i = 0; \$i < \$count; \$i++) {
            \Illuminate\Support\Facades\DB::table('campaign_steps')->insert([
                'business_id' => \$tenant['id'],
                'campaign_id' => 1,
                'step_number' => \$i + 1,
                'channel' => 'sms',
                'template_name' => 'hello',
                'delay_days' => 1,
                'person_id' => \$customerId,
                'recipient' => \$phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        return ['id' => \$customerId, 'phone' => \$phone];
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
