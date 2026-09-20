<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
foreach ($chunks as $chunk) {
    if (preg_match('/^@module\s+\*{0,2}X-116\b/', $chunk)) {
        echo "CHUNK FOR X-116:\n";
        preg_match_all(
            '/@agent_reachable\s+((?:`?[a-z*][a-z0-9_.*]*`?)(?:[^\n]*))/i',
            $chunk,
            $all,
            PREG_SET_ORDER
        );
        print_r($all);
    }
}
