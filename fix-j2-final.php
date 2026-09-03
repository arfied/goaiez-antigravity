<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

// 1. Inject addToPool in signUp
\$content = preg_replace(
    '/(.*\$owner = \\\\App\\\\Models\\\\User::factory\(\)->create\(\);.*)/',
    "\$1\n        app(\\\\App\\\\Services\\\\Sms\\\\TenantNumbers::class)->addToPool('+15125550999');",
    \$content
);

// 2. Fix waitForProvisionedNumber
\$content = preg_replace(
    '/(\$run = \\\\Illuminate\\\\Support\\\\Facades\\\\DB::table\()\'onboarding_runs\'(\)->where\(\'business_id\', \$tenant\[\'id\'\]\)->first\(\);)/',
    "\\\$number = \\\\Illuminate\\\\Support\\\\Facades\\\\DB::table('phone_numbers')->where('business_id', \\\$tenant['id'])->first();",
    \$content
);
\$content = str_replace(
    "return \$run ? \$run->provisioned_number : '';",
    "return \$number ? \$number->e164 : '';",
    \$content
);

// 3. Fix placeRealCallTo
\$targetPlaceCall = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('phone_number', \$number)->first();
        \App\Support\Tenancy::set(\$pool->business_id);
        \$biz = \App\Models\Business::find(\$pool->business_id);
        \$owner = \$biz->owner;
        \$caller = \$owner ? \$owner->email : '+12622164033';
        if (strpos(\$caller, '@') !== false) {
            \$caller = explode('@', \$caller)[0];
        }
PHP;
\$replacePlaceCall = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('e164', \$number)->first();
        \App\Support\Tenancy::set(\$row->business_id);
        \$biz = \App\Models\Business::find(\$row->business_id);
        \$owner = \$biz->owner;
        \$caller = '+12622164033';
PHP;
\$content = str_replace(\$targetPlaceCall, \$replacePlaceCall, \$content);

// 4. Fix placeRealCallTo query after drainQueue
\$targetAfterDrain = <<<PHP
        \$this->drainQueue();
        
        // Find the call
PHP;
\$replaceAfterDrain = <<<PHP
        \$this->drainQueue();
        \App\Support\Tenancy::set(\$biz->id); // RESTORE TENANCY AFTER JOB CLEARS IT
        
        // Find the call
PHP;
\$content = str_replace(\$targetAfterDrain, \$replaceAfterDrain, \$content);

// 5. Fix postCarrierWebhook to use phone_numbers
\$targetPost = <<<PHP
        \$to = env('INFOBIP_SENDER', '+19015922708');
        // If the business has a number, use it!
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('business_id', \$tenant['id'])->first();
        if (\$pool) {
            \$to = \$pool->phone_number;
        }
PHP;
\$replacePost = <<<PHP
        \$to = env('INFOBIP_SENDER', '+19015922708');
        // If the business has a number, use it!
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        if (\$row) {
            \$to = \$row->e164;
        }
PHP;
\$content = str_replace(\$targetPost, \$replacePost, \$content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', \$content);
