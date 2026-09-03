<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
        \$response = \$this->post(route('account.plan.cancel'), ['confirm' => true]);
        if (\$response->status() !== 200 && \$response->status() !== 302) {
            dd(\$response->content());
        }
        if (\$response->status() === 302 && session()->has('errors')) {
            dd(session('errors'));
        }
PHP;
$replace = <<<PHP
        \$response = \$this->post(route('account.plan.cancel'), ['confirm' => true]);
        
        \$sub = \Illuminate\Support\Facades\DB::table('subscriptions')->where('business_id', \$tenant['id'])->first();
        if (\$sub->cancellation_requested_at === null && \$sub->status !== 'canceled') {
            dd(session()->all());
        }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
