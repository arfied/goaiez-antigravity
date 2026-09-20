<?php
$output = shell_exec('php artisan doctor --stage=capability');
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);
$ids = array_map(function($m) { return $m[2]; }, $matches);

$tracker = file_get_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md');
foreach ($ids as $id) {
    // We want to replace the LAST pipe on the line that starts with "| $id |"
    $tracker = preg_replace('/^(\|\s*' . $id . '\s*\|.*?)\s*\|$/m', '$1 refuses |', $tracker);
}
file_put_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md', $tracker);
echo "Fixed via preg_replace.\n";
