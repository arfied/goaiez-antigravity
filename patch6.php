<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search1 = <<<PHP
        \$this->drainQueue();

        \$turn = DB::table('agent_turns')
PHP;
$replace1 = <<<PHP
        \$this->drainQueue();
        \App\Support\Tenancy::set(\$tenant['id']); // RESTORE TENANCY

        \$turn = DB::table('agent_turns')
PHP;
$content = str_replace($search1, $replace1, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
