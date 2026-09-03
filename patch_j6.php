<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
        \Illuminate\Support\Facades\DB::table('subscriptions')->insertOrIgnore([
            'business_id' => \$tenant['id'],
            'gateway' => 'stripe',
            'gateway_id' => 'sub_123',
            'plan' => 'growth',
            'status' => 'active',
            'term' => 'monthly',
        ]);
PHP;
$replace = <<<PHP
        \Illuminate\Support\Facades\DB::table('subscriptions')->insertOrIgnore([
            'business_id' => \$tenant['id'],
            'gateway' => 'stripe',
            'stripe_customer_id' => 'cus_123',
            'stripe_subscription_id' => 'sub_123',
            'plan' => 'growth',
            'status' => 'active',
            'term' => 'monthly',
        ]);
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
