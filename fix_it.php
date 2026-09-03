<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$content = str_replace("\App\Modules!1", "\App\Modules\X211", $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
