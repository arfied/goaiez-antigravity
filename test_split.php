<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
foreach ($chunks as $chunk) {
    if (strpos($chunk, 'X-116') !== false) {
        echo "CHUNK FOR X-116:\n";
        echo substr($chunk, 0, 500) . "\n...\n";
        preg_match_all(
            '/@agent_reachable\s+((?:`?[a-z*][a-z0-9_.*]*`?)(?:[^\n]*))/i',
            $chunk,
            $all,
            PREG_SET_ORDER
        );
        print_r($all);
    }
}
