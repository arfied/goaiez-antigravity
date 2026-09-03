<?php
$content = file_get_contents('app/tests/Journeys/TwelveJourneysTest.php');
$target = "\App\Enums\CreditKind::ManualAdjustment";
$replacement = "\App\Enums\CreditKind::Adjust";
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/TwelveJourneysTest.php', $content);
