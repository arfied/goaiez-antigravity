<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$biz = \App\Models\Business::whereHas('phoneNumbers', function(\$q) use (\$number) {
            \$q->where('e164', \$number);
        })->first();
PHP;
$replacement = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('e164', \$number)->first();
        \$biz = \App\Models\Business::find(\$row->business_id);
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
