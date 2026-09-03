<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
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

        \$to = env('INFOBIP_SENDER', '+19015922708');
        // If the business has a number, use it!
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('business_id', \$tenant['id'])->first();
        if (\$pool) {
            \$to = \$pool->phone_number;
        }
PHP;
$replacement = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$biz = \App\Models\Business::whereHas('phoneNumbers', function(\$q) use (\$number) {
            \$q->where('e164', \$number);
        })->first();
        \App\Support\Tenancy::set(\$biz->id);
        \$owner = \$biz->owner;
        \$caller = \$owner ? \$owner->email : '+12622164033';
        if (strpos(\$caller, '@') !== false) {
            \$caller = explode('@', \$caller)[0];
        }

        \$to = \$number;
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
