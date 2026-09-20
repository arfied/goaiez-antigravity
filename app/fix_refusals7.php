<?php
$output = file_get_contents('doctor.txt');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
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
            $idx = count($parts) - 2;
            $parts[$idx] = rtrim($parts[$idx]) . ' refuses ';
            $line = implode('|', $parts);
            $count++;
        }
    }
}

file_put_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md', implode("\n", $lines));
echo "Fixed $count refusals.\n";
