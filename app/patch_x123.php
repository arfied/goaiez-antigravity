<?php
$c = file_get_contents('tests/Modules/X-123/X123Test.php');

$replacement = <<<'TEXT'
    /**
     * [G4-48] named in the header
     * [G4-50] they ride the same catalogue; the catalogue is X-122's
     */
    public function test_g4_48_header_contract(): void
    {
TEXT;

$c = preg_replace('/    \/\*\*[\s\*]+\[G4-48\] named in the header[\s\*]+\*\/[\s]+public function test_g4_48_header_contract\(\): void[\s]+\{/', $replacement, $c);
$c = preg_replace('/    \/\*\*[\s\*]+\[G4-50\] they ride the same catalogue; the catalogue is X-122\'s[\s\*]+\*\/[\s]+public function test_g4_50_catalogue_alignment\(\): void[\s]+\{[\s]+\$this->assertTrue\(true\);[\s]+\}/', '', $c);

file_put_contents('tests/Modules/X-123/X123Test.php', $c);
