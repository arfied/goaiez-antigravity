<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
$new_chunks = [];

foreach ($chunks as $chunk) {
    if (preg_match('/^@module\s+\*{0,2}(X|C)-[A-Za-z0-9]+\b/', $chunk)) {
        // Find @agent_reachable in this chunk
        if (preg_match('/@agent_reachable\s+((?:`?[a-z*][a-z0-9_.*]*`?)(?:[^\n]*))/i', $chunk, $match)) {
            $value = $match[1];
            // If it doesn't contain 'none', append ' · none' to the END of the value, BEFORE the next @ or end of line.
            // But wait, the value matched by `[^\n]*` might contain other tags if they are on the same line.
            // Actually, in the plan, @agent_reachable is often followed by ` · @emits`.
            // So let's extract the part BEFORE the next @ tag.
            
            $line = $match[0];
            $parts = preg_split('/(?=·\s*@[a-z_]+)/', $line, 2);
            $reachable_part = $parts[0];
            $rest = $parts[1] ?? '';
            
            if (strpos($reachable_part, 'none') === false) {
                // Remove trailing backticks or spaces
                $reachable_part = rtrim($reachable_part, " \t`");
                $new_reachable = $reachable_part . "` · none ";
                // Now replace the original line in the chunk
                $chunk = str_replace($line, $new_reachable . $rest, $chunk);
            }
        }
    }
    $new_chunks[] = $chunk;
}

$new_plan = implode('', $new_chunks);
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $new_plan);
