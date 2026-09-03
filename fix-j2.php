<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
    private function signUp(string \$businessName, string \$phone): array
    {
        \$owner = \App\Models\User::factory()->create();
        \$res = app(\App\Modules\X118\Actions\OnboardingStartAction::class)->handle(\$owner, \$businessName, \$phone);
PHP;
$replacement = <<<PHP
    private function signUp(string \$businessName, string \$phone): array
    {
        \$owner = \App\Models\User::factory()->create();
        // The core platform needs a number in the pool for TenantProvisioner to claim it!
        app(\App\Services\Sms\TenantNumbers::class)->addToPool('+15125550999');
        
        \$res = app(\App\Modules\X118\Actions\OnboardingStartAction::class)->handle(\$owner, \$businessName, \$phone);
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
