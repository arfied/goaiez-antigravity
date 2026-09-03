<?php
$cache = [];
foreach (['app/GOAIEZ-TRACKER-CAPABILITIES.md', 'app/GOAIEZ-MASTER-PLAN.md'] as $file) {
    foreach (explode("\n", (string) file_get_contents($file)) as $line) {
        if (! str_starts_with($line, '|')) continue;
        if (strpos($line, 'G3-02') === false) continue; // just debug G3-02
        
        $cells = array_map('trim', explode('|', $line));
        
        // Exact regex from CapabilitiesScaffoldCommand
        if (preg_match_all('/\b(G\d+-\d+|N-\d+(?:-\d+)?)\b/', $cells[1] ?? '', $m) === 0) {
            continue;
        }
        $tail = array_values(array_filter(array_slice($cells, 2)));
        if ($tail === []) continue;
        $a = trim((string) preg_replace('/[⭐⛔⚠️✅*`]/u', '', (string) end($tail)));
        print("FOUND G3-02 in $file:\n");
        print("  Original line: $line\n");
        print("  Parsed tail end: $a\n");
    }
}
