<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$content = str_replace(
    "'ssl' => isset(\$version->ssl_installed) ? (bool) \$version->ssl_installed : false,",
    "'ssl' => isset(\$version->ssl_enabled) ? (bool) \$version->ssl_enabled : false,",
    $content
);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
