<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$lines = explode("\n", $plan);
$c = 0;
foreach ($lines as $line) {
    if (!str_starts_with($line, '|')) continue;
    $cells = explode('|', $line);
    if (count($cells) < 6) continue;
    if (preg_match_all('/(G\d+-\d+|N-\d+(?:-\d+)?)/', $cells[1], $m)) {
        $c++;
    }
}
var_dump($c);
