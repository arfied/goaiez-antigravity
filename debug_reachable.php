<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
foreach ($chunks as $chunk) {
    if (preg_match('/^@module\s+\*{0,2}(X-221|X-222|X-223|C-Agent)\b/', $chunk, $m)) {
        if (preg_match('/@agent_reachable\s+((?:`?[a-z*][a-z0-9_.*]*`?)(?:[^\n]*))/i', $chunk, $match)) {
            $line = $match[0];
            $parts = preg_split('/(?=·\s*@[a-z_]+)/', $line, 2);
            $reachable_part = $parts[0];
            $rest = $parts[1] ?? '';
            
            if (strpos($reachable_part, 'none') === false) {
                echo $m[1] . " NEEDS NONE: " . $reachable_part . "\n";
            } else {
                echo $m[1] . " HAS NONE: " . $reachable_part . "\n";
            }
        }
    }
}
