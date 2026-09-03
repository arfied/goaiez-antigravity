<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$content = str_replace("\$owner = \$biz->owner();", "\$owner = \$biz->owner;", $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
