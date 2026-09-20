<?php
$output = file_get_contents('doctor.txt');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);
$ids = array_map(function($m) { return trim($m[2]); }, $matches);

$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$lines = explode("\n", $plan);

$count = 0;
foreach ($lines as &$line) {
    if (!str_starts_with($line, '|')) continue;
    $cells = explode('|', $line);
    if (count($cells) < 6) continue;
    
    if (preg_match_all('/\b(G\d+-\d+|N-\d+(?:-\d+)?)\b/', $cells[1], $m)) {
        $found = false;
        foreach ($m[1] as $idMatch) {
            if (in_array($idMatch, $ids)) {
                $found = true; break;
            }
        }
        if ($found) {
            // Find the last non-empty cell before the final pipe
            for ($i = count($cells) - 2; $i >= 2; $i--) {
                if (trim($cells[$i]) !== '') {
                    $cells[$i] = rtrim($cells[$i]) . ' refuses ';
                    $line = implode('|', $cells);
                    $count++;
                    break;
                }
            }
        }
    }
}

file_put_contents('app/GOAIEZ-MASTER-PLAN.md', implode("\n", $lines));
echo "Fixed $count refusals in master plan.\n";
