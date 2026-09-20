<?php
$output = file_get_contents('app/doctor.txt');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);
$ids = array_map(function($m) { return trim($m[2]); }, $matches);

$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$lines = explode("\n", $plan);
foreach ($lines as $line) {
    if (!str_starts_with($line, '|')) continue;
    $cells = explode('|', $line);
    if (count($cells) < 6) continue;
    if (preg_match_all('/(G\d+-\d+|N-\d+(?:-\d+)?)/', $cells[1], $m)) {
        foreach ($m[1] as $idMatch) {
            if ($idMatch === 'G5-01') {
                var_dump($idMatch);
                var_dump(in_array($idMatch, $ids));
            }
        }
    }
}
