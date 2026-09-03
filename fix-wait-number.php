<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = "    private function waitForProvisionedNumber(array \$tenant, int \$timeoutSeconds): string\n    {\n        throw \$this->todo('poll until the carrier returns a real number');\n    }";
$replacement = <<<PHP
    private function waitForProvisionedNumber(array \$tenant, int \$timeoutSeconds): string
    {
        \$this->drainQueue();
        \$number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        return \$number ? \$number->e164 : '';
    }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
