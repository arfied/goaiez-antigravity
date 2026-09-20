<?php
$output = shell_exec('php artisan doctor --stage=capability');
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);

$ids = array_map(function($m) { return $m[2]; }, $matches);

$tracker = file_get_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md');
$lines = explode("\n", $tracker);
$count = 0;
foreach ($lines as &$line) {
    if (preg_match('/^\|\s*(G\d+-\d+|N-\d+(?:-\d+)?)\s*\|/', $line, $m)) {
        if (in_array($m[1], $ids)) {
            $line = rtrim($line);
            $line = preg_replace('/\|\s*$/', ' refuses |', $line);
            $count++;
        }
    }
}
file_put_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md', implode("\n", $lines));
echo "Fixed $count refusals.\n";
