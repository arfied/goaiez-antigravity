<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        // Simulate a real inbound call using the vendor webhook
        // The test created a business with phone +15550777. The owner is calling.
        // But we don't have the owner's number easily available. We can look it up.
        \$biz = \App\Models\Business::whereHas('phoneNumbers', function(\$q) use (\$number) {
            \$q->where('e164', \$number);
        })->first();
        \$owner = \$biz->owner();
        \$caller = \$owner ? \$owner->email : '+12622164033'; // in signup, email was set to phone@example.com, or we just pass the number.
        if (strpos(\$caller, '@') !== false) {
            \$caller = explode('@', \$caller)[0];
        }
PHP;
$replacement = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('phone_number', \$number)->first();
        \$biz = \App\Models\Business::find(\$pool->business_id);
        \$owner = \$biz->owner();
        \$caller = \$owner ? \$owner->email : '+12622164033';
        if (strpos(\$caller, '@') !== false) {
            \$caller = explode('@', \$caller)[0];
        }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
