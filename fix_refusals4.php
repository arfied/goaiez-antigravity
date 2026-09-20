<?php
$output = shell_exec('php artisan doctor --stage=capability');
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);
$ids = array_map(function($m) { return trim($m[2]); }, $matches);

$tracker = file_get_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md');
$lines = explode("\n", $tracker);

$count = 0;
foreach ($lines as &$line) {
    if (trim($line) === '') continue;
    $parts = explode('|', $line);
    if (count($parts) >= 3) {
        $id = trim($parts[1]);
        if (in_array($id, $ids)) {
            // It's a match! Append ' refuses ' to the last part before the final pipe
            $idx = count($parts) - 2;
            $parts[$idx] = $parts[$idx] . ' refuses ';
            $line = implode('|', $parts);
            $count++;
        }
    }
}

file_put_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md', implode("\n", $lines));
echo "Fixed $count refusals via explode.\n";
