<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
        // The core platform needs a number in the pool for TenantProvisioner to claim it!
        app(\App\Services\Sms\TenantNumbers::class)->addToPool('+15125550999');
PHP;
$replacement = <<<PHP
        // The core platform needs a number in the pool for TenantProvisioner to claim it!
        try {
            \$num = app(\App\Services\Sms\TenantNumbers::class)->addToPool('+15125550999');
            \Illuminate\Support\Facades\Log::warning("Added to pool: " . \$num->id . " e164: " . \$num->e164);
        } catch (\Throwable \$e) {
            \Illuminate\Support\Facades\Log::warning("addToPool failed: " . \$e->getMessage());
        }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
