<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

// 1. addToPool
$content = preg_replace(
    '/(.*\$owner = \\\\App\\\\Models\\\\User::factory\(\)->create\(\);.*)/',
    "\$1\n        app(\\\\App\\\\Services\\\\Sms\\\\TenantNumbers::class)->addToPool('+15125550999');",
    $content
);

// 2. waitForProvisionedNumber
$target2 = <<<PHP
    private function waitForProvisionedNumber(array \$tenant, int \$timeoutSeconds): string
    {
        \$this->drainQueue();
        \$run = \Illuminate\Support\Facades\DB::table('onboarding_runs')->where('business_id', \$tenant['id'])->first();
        return \$run ? \$run->provisioned_number : '';
    }
PHP;
$replace2 = <<<PHP
    private function waitForProvisionedNumber(array \$tenant, int \$timeoutSeconds): string
    {
        \$this->drainQueue();
        \$number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        return \$number ? \$number->e164 : '';
    }
PHP;
$content = str_replace($target2, $replace2, $content);

// 3. postCarrierWebhook
$target3 = <<<PHP
        \$to = env('INFOBIP_SENDER', '+19015922708');
        // If the business has a number, use it!
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('business_id', \$tenant['id'])->first();
        if (\$pool) {
            \$to = \$pool->phone_number;
        }
PHP;
$replace3 = <<<PHP
        \$to = env('INFOBIP_SENDER', '+19015922708');
        // If the business has a number, use it!
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', \$tenant['id'])->first();
        if (\$row) {
            \$to = \$row->e164;
        }
PHP;
$content = str_replace($target3, $replace3, $content);

// 4. placeRealCallTo
$target4 = <<<PHP
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
        
        \$this->postCarrierWebhook(\$biz->toArray(), 'call.answered', \$caller);
        \$this->drainQueue();
        
        // Find the call
PHP;
$replace4 = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('e164', \$number)->first();
        \App\Support\Tenancy::set(\$row->business_id);
        \$biz = \App\Models\Business::find(\$row->business_id);
        \$owner = \$biz->owner;
        \$caller = '+12622164033';
        
        \$this->postCarrierWebhook(\$biz->toArray(), 'call.answered', \$caller);
        \$this->drainQueue();
        \App\Support\Tenancy::set(\$biz->id); // RESTORE TENANCY
        
        // Find the call
PHP;
$content = str_replace($target4, $replace4, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
