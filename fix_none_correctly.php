<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

// Remove the wrongly appended ` · none that we did
$plan = str_replace('` · none ', '', $plan);

// Insert ` · none ` before ⭐ or ⛔ or *( if it doesn't already exist
$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
$new_chunks = [];

foreach ($chunks as $chunk) {
    if (preg_match('/^@module\s+\*{0,2}(X|C)-[A-Za-z0-9]+\b/', $chunk)) {
        if (preg_match('/@agent_reachable[^\n]*/', $chunk, $match)) {
            $line = $match[0];
            $parts = preg_split('/(\*\(|⭐|⛔|⚠️|\s+—\s+)/u', $line, 2, PREG_SPLIT_DELIM_CAPTURE);
            $reachable = $parts[0];
            if (strpos($reachable, 'none') === false) {
                $reachable = rtrim($reachable, " `\t");
                // if it originally ended with backtick, keep it? No, DeclarationParser strips backticks anyway.
                // Just append ` · none `
                $new_line = $reachable . " · none " . ($parts[1] ?? '') . ($parts[2] ?? '');
                $chunk = str_replace($line, $new_line, $chunk);
            }
        }
    }
    $new_chunks[] = $chunk;
}

file_put_contents('app/GOAIEZ-MASTER-PLAN.md', implode('', $new_chunks));
