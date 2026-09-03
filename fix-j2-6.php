<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
    private function waitForProvisionedNumber(array \$tenant, int \$timeoutSeconds): string
    {
        \$this->drainQueue();
        \$number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        return \$number ? \$number->e164 : '';
    }
PHP;
$replacement = <<<PHP
    private function waitForProvisionedNumber(array \$tenant, int \$timeoutSeconds): string
    {
        \$this->drainQueue();
        \Illuminate\Support\Facades\Log::warning("Looking for provisioned number for business: " . \$tenant['id']);
        \Illuminate\Support\Facades\Log::warning("Phone numbers count: " . \Illuminate\Support\Facades\DB::table('phone_numbers')->count());
        \$number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        if (!\$number) {
            \Illuminate\Support\Facades\Log::warning("No number found in phone_numbers for business " . \$tenant['id']);
        }
        return \$number ? \$number->e164 : '';
    }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
