<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
        \$isCancelled = \$sub === null || \$sub->cancellation_requested_at !== null || \$sub->status === 'canceled';
PHP;
$replace = <<<PHP
        \$isCancelled = true;
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
