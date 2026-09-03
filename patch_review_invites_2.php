<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function reviewInvitesFor(array \$person): array
    {
        return \Illuminate\Support\Facades\DB::table('outreach_messages')
            ->where('customer_id', \$person['id'])
            ->whereIn('purpose', ['review_request', 'reminder'])
            ->get()
            ->toArray();
    }
PHP;
$replace = <<<PHP
    private function reviewInvitesFor(array \$person): array
    {
        return \Illuminate\Support\Facades\DB::table('review_requests')
            ->where('customer_id', \$person['id'])
            ->get()
            ->toArray();
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
