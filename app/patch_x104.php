<?php
$c = file_get_contents('tests/Modules/X-104/X104Test.php');

$replacement = <<<'TEXT'
    /**
     * [G3-60], [G6-26], [G6-34], [G6-35], [G7-46], [G8-38], [G19-16]
     * Plugin agency branding, universal takeover path & sync
     */
    public function test_g10_45_plugin_creation_and_config(): void
TEXT;

$c = preg_replace('/    public function test_g10_45_plugin_creation_and_config\(\): void/', $replacement, $c);
$c = preg_replace('/    \/\*\*[\s\*]+\[G3-60\], \[G6-26\], \[G6-34\], \[G6-35\], \[G7-46\], \[G8-38\], \[G19-16\][\s\*]+Plugin agency branding, universal takeover path & sync[\s\*]+\*\/[\s]+public function test_plugin_capabilities\(\): void[\s]+\{[\s]+\$this->assertTrue\(true\);[\s]+\}/', '', $c);

file_put_contents('tests/Modules/X-104/X104Test.php', $c);
