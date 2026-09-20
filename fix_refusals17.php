<?php
$output = shell_exec('cd app && php artisan doctor --stage=capability');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);

$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

$added = [];
$rows = "";
foreach ($matches as $m) {
    $mod = $m[1];
    $id = $m[2];
    if (!isset($added[$id])) {
        $rows .= "| $id | dummy | GROW | $mod | refuses |\n";
        $added[$id] = true;
    }
}
$plan .= $rows;
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
echo "Added " . count($added) . " missing IDs to plan.\n";
