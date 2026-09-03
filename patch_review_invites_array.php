<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function reviewInvitesFor(array \$person): array
    {
        return \Illuminate\Support\Facades\DB::table('review_requests')
            ->where('customer_id', \$person['id'])
            ->get()
            ->toArray();
    }
PHP;
$replace = <<<PHP
    private function reviewInvitesFor(array \$person): array
    {
        \$rows = \Illuminate\Support\Facades\DB::table('review_requests')
            ->where('customer_id', \$person['id'])
            ->get();
        return json_decode(json_encode(\$rows), true);
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
