<?php
$tracker = file_get_contents('app/GOAIEZ-TRACKER-CAPABILITIES.md');
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

$caps = [];
foreach (explode("\n", $tracker) as $line) {
    if (! str_starts_with($line, '|')) continue;
    $c = array_map('trim', explode('|', $line));
    if (preg_match('/^⭐?\s*\*{0,2}(G\d+-\d+|N-\d+(?:-\d+)?)/u', $c[1] ?? '', $m) !== 1) continue;
    $id = $m[1];
    $parentCell = ($c[4] ?? '') !== '' ? $c[4] : ($c[2] ?? '');
    $caps[$id] = ['assertion' => '', 'parent' => $parentCell];
}

foreach (explode("\n", $plan) as $line) {
    if (! str_starts_with($line, '|')) continue;
    $cells = array_map('trim', explode('|', $line));
    if (count($cells) < 6) continue;
    if (preg_match_all('/\b(G\d+-\d+|N-\d+(?:-\d+)?)\b/', $cells[1] ?? '', $m) === 0) continue;
    $tail = array_values(array_filter(array_slice($cells, 2)));
    if ($tail === []) continue;
    $a = trim((string) preg_replace('/[⭐⛔⚠️✅*`]/u', '', (string) end($tail)));
    if ($a === '') continue;
    foreach ($m[1] as $id) {
        if (! isset($caps[$id])) continue;
        if ($id === 'N-054') {
            echo "Found N-054 in plan! a = $a\n";
        }
        $caps[$id]['assertion'] = $a;
    }
}
var_dump($caps['N-054']['assertion']);
