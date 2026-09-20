<?php
$raw = 'template.score';
$parts = array_values(array_filter(array_map(
    static fn (string $s): string => trim($s),
    preg_split('/[·,]/u', $raw) ?: []
)));

$res = $parts === []
    ? '[]'
    : "[\n        '".implode("',\n        '", $parts)."',\n    ]";
echo $res . "\n";
