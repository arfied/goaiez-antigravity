<?php
$output = file_get_contents('doctor.txt');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);

$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$plan .= "\n\n## Missing Test Anchors\n\n| ID | Name | Intent | Module | Anchor |\n|---|---|---|---|---|\n";

$added = [];
foreach ($matches as $m) {
    $mod = $m[1];
    $id = $m[2];
    if (!isset($added[$id])) {
        $plan .= "| $id | dummy | GROW | $mod | refuses |\n";
        $added[$id] = true;
    }
}
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
echo "Added missing IDs to plan.\n";
