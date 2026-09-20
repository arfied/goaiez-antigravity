<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
$new_chunks = [];

foreach ($chunks as $chunk) {
    if (preg_match('/^@module\s+\*{0,2}((?:X|C)-[A-Za-z0-9]+)\b/', $chunk, $m)) {
        $mod = $m[1];
        if (in_array($mod, ['X-127', 'X-217', 'X-218'])) {
            $chunk = preg_replace('/@emits\s*`/', '@emits none`', $chunk);
        }
    }
    $new_chunks[] = $chunk;
}
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', implode('', $new_chunks));
