<?php
$tracker = file_get_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md');
$lines = explode("\n", $tracker);
$first_few = [];
foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $parts = explode('|', $line);
    if (count($parts) >= 3) {
        $id = trim($parts[1]);
        if (preg_match('/^(G\d+-\d+|N-\d+(?:-\d+)?)$/', $id)) {
            $first_few[] = $id;
        }
    }
}
var_dump(array_slice($first_few, 0, 5));
