<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$content = preg_replace('/app\(\\\\App\\\\Modules.8\\\\Domain\\\\GatewayEngine::class\)/', 'app(\\\\App\\\\Modules\\\\X198\\\\Domain\\\\GatewayEngine::class)', $content);
$content = preg_replace('/app\(\\\\App\\\\Modules.9\\\\Domain\\\\InvoiceEngine::class\)/', 'app(\\\\App\\\\Modules\\\\X199\\\\Domain\\\\InvoiceEngine::class)', $content);

// And wait! The Log::warning lines have $turn and $refusal which are undefined!
$content = preg_replace('/\\\\Illuminate\\\\Support\\\\Facades\\\\Log::warning\("Agent reply.*?;\n/', '', $content);
$content = preg_replace('/\\\\Illuminate\\\\Support\\\\Facades\\\\Log::warning\("Agent turns.*?;\n/', '', $content);
$content = preg_replace('/\\\\Illuminate\\\\Support\\\\Facades\\\\Log::warning\("Failed jobs.*?;\n/', '', $content);
$content = preg_replace('/\\\\Illuminate\\\\Support\\\\Facades\\\\Log::warning\("Agent refusals.*?;\n/', '', $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
