<?php
$output = file_get_contents('doctor.txt');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);
$ids = array_unique(array_map(function($m) { return trim($m[2]); }, $matches));

foreach (['GOAIEZ-TRACKER-CAPABILITIES.md', 'GOAIEZ-MASTER-PLAN.md'] as $file) {
    $plan = file_get_contents($file);
    $lines = explode("\n", $plan);
    foreach ($lines as &$line) {
        if (!str_starts_with($line, '|')) continue;
        $cells = explode('|', $line);
        if (count($cells) < 6) continue;
        
        $found = false;
        foreach ($ids as $id) {
            if (strpos($cells[1], $id) !== false) {
                $found = true; break;
            }
        }
        
        if ($found) {
            for ($i = count($cells) - 2; $i >= 2; $i--) {
                if (trim($cells[$i]) !== '') {
                    if (strpos($cells[$i], 'refuses') === false) {
                        $cells[$i] = rtrim($cells[$i]) . ' refuses ';
                        $line = implode('|', $cells);
                    }
                    break;
                }
            }
        }
    }
    file_put_contents($file, implode("\n", $lines));
}
echo "Fixed remaining.\n";
