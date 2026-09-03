<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
    private function signUp(string \$businessName, string \$phone): array
    {
        \$owner = \App\Models\User::factory()->create();
        // The core platform needs a number in the pool for TenantProvisioner to claim it!
        app(\App\Services\Sms\TenantNumbers::class)->addToPool('+15125550999');
PHP;
$replacement = <<<PHP
    private function signUp(string \$businessName, string \$phone): array
    {
        \$owner = \App\Models\User::factory()->create();
        // The core platform needs a number in the pool for TenantProvisioner to claim it!
        app(\App\Services\Sms\TenantNumbers::class)->addToPool('+15125550999');
        \Illuminate\Support\Facades\Log::info("Pool count before signUp: " . \Illuminate\Support\Facades\DB::table('phone_numbers')->count());
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
