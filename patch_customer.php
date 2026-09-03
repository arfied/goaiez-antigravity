<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$content = str_replace("DB::table('customers')", "DB::table('people')", $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
