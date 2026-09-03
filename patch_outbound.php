<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function outboundSince(array \$person, string \$marker): int
    {
        throw \$this->todo('count outbound to this person AFTER the STOP was received');
    }
PHP;
$replace = <<<PHP
    private function outboundSince(array \$person, string \$marker): int
    {
        \$personId = \$person['id'];
        
        \$conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->where('customer_id', \$personId)
            ->pluck('id');
            
        \$messageCount = \Illuminate\Support\Facades\DB::table('messages')
            ->whereIn('conversation_id', \$conversations)
            ->where('direction', 'outbound')
            ->count();
            
        \$campaignCount = \Illuminate\Support\Facades\DB::table('campaign_steps')
            ->where('person_id', \$personId)
            ->whereNotNull('sent_at')
            ->count();
            
        return \$messageCount + \$campaignCount;
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
