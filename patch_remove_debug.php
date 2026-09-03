<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
        \$sub_before = \Illuminate\Support\Facades\DB::table('subscriptions')->where('business_id', \$tenant['id'])->first();
        if (\$sub_before === null) {
            dd("sub before is null!");
        }
        if (\$sub_before->stripe_subscription_id === null) {
            dd("stripe_subscription_id is null!", \$sub_before);
        }
        
        \$response = \$this->post(route('account.plan.cancel'), ['confirm' => true]);
        
        \$sub = \Illuminate\Support\Facades\DB::table('subscriptions')->where('business_id', \$tenant['id'])->first();
        if (\$sub->cancellation_requested_at === null && \$sub->status !== 'canceled') {
            dd(session()->all(), \$sub_before);
        }
PHP;
$replace = <<<PHP
        \$response = \$this->post(route('account.plan.cancel'), ['confirm' => true]);
        \$sub = \Illuminate\Support\Facades\DB::table('subscriptions')->where('business_id', \$tenant['id'])->first();
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
