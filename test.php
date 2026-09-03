<?php
$harness = file_get_contents('app/tests/Journeys/JourneyHarness.php');
if (strpos($harness, 'postCarrierWebhook') !== false) {
    echo "Found it\n";
}
