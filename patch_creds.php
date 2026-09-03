<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
        \Illuminate\Support\Facades\Http::fake([
PHP;
$replace = <<<PHP
        \App\Models\PlatformCredential::updateOrCreate(['key' => 'anthropic_api_key', 'environment' => \App\Enums\CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        \App\Models\PlatformCredential::updateOrCreate(['key' => 'openai_api_key', 'environment' => \App\Enums\CredentialEnvironment::Live], ['value' => 'test_key', 'rotated_at' => now(), 'rotated_by' => 'system']);
        \Illuminate\Support\Facades\Http::fake([
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
