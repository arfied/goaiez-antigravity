<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('phone_number', \$number)->first();
        \$biz = \App\Models\Business::find(\$pool->business_id);
PHP;
$replacement = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$pool = \Illuminate\Support\Facades\DB::table('number_pool')->where('phone_number', \$number)->first();
        \App\Support\Tenancy::set(\$pool->business_id);
        \$biz = \App\Models\Business::find(\$pool->business_id);
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
