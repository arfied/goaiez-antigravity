<?php
$c = file_get_contents('tests/Modules/C-Ai/CAiTest.php');
$c = preg_replace('/    \/\*\*[\s\*]+\[G2-27\] the plan\'s mechanism is grounding \+ lexicon \+ the teaching box \(P-097\); a per-tenant fine-tune is an owner question[\s\*]+\*\/[\s]+public function test_g2_27_grounding_lexicon_mechanism\(\): void[\s]+\{[\s]+\$this->assertTrue\(true\);[\s]+\}/', '', $c);
$c = preg_replace('/    \/\*\*[\s\*]+\[G5-22\]/', "    /**\n     * [G2-27] the plan's mechanism is grounding + lexicon + the teaching box (P-097); a per-tenant fine-tune is an owner question\n     * [G5-22]", $c);
file_put_contents('tests/Modules/C-Ai/CAiTest.php', $c);
