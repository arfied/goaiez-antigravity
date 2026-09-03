<?php
$content = file_get_contents('app/tests/Journeys/TwelveJourneysTest.php');
$target = "        \$tenant = \$this->tenantWithLiveNumber();\n        \$started = microtime(true);";
$replacement = <<<PHP
        \$tenant = \$this->tenantWithLiveNumber();
        \$started = microtime(true);
        \App\Support\Tenancy::actingAs(\$tenant['id'], function() {
            app(\App\Services\Billing\CreditLedger::class)->record(
                \App\Enums\CreditProduct::Sms,
                \App\Enums\CreditKind::ManualAdjustment,
                100,
                'system',
                'test top up'
            );
        });
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/TwelveJourneysTest.php', $content);
