<?php
$cache = [];
foreach (['app/GOAIEZ-TRACKER-CAPABILITIES.md', 'app/GOAIEZ-MASTER-PLAN.md'] as $file) {
    foreach (explode("\n", (string) file_get_contents($file)) as $line) {
        if (! str_starts_with($line, '|')) continue;
        $cells = array_map('trim', explode('|', $line));
        if (preg_match('/^\s*\|?\s*(?:⭐\s*)?\*\*(G\d+-\d+|N-\d+(?:-\d+)?)\*\*\s*\|/', $line, $m) !== 1) {
            
            // Wait, does the regex require ** ? Let me check the exact regex from the file!
            $src = file_get_contents('app/app/Console/Commands/CapabilitiesScaffoldCommand.php');
            preg_match('/preg_match\(\'(.*?)\'/', $src, $regex);
            
            if (preg_match($regex[1], $line, $m2) === 1) {
                // matched with actual regex
                $m = $m2;
            } else {
                continue;
            }
        }
        $tail = array_values(array_filter(array_slice($cells, 2)));
        if ($tail === []) continue;
        $a = trim((string) preg_replace('/[⭐⛔⚠️✅*`]/u', '', (string) end($tail)));
        if (mb_strlen($a) < 12) continue;
        foreach ($m[1] as $id) {
            $cache[$id] ??= $a;
        }
    }
}
print_r([
    'G3-02' => $cache['G3-02'] ?? 'not found',
    'G5-25' => $cache['G5-25'] ?? 'not found',
    'G13-38' => $cache['G13-38'] ?? 'not found',
]);
