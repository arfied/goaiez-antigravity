<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$content = preg_replace('/app\(\\\\App\\\\Modules.1\\\\Actions\\\\JobStateAction::class\)/', 'app(\\\\App\\\\Modules\\\\X171\\\\Actions\\\\JobStateAction::class)', $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
