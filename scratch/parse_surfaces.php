<?php
$modulesDir = __DIR__ . '/../app/app/Modules';
$trackerFile = __DIR__ . '/../app/GOAIEZ-TRACKER-MODULES.md';
$planFile = __DIR__ . '/../app/GOAIEZ-MASTER-PLAN.md';

$trackerLines = file($trackerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$moduleGroups = [];
foreach ($trackerLines as $line) {
    if (preg_match('/^\|\s*([C|X]-[^\|]+?)\s*\|\s*([^\|]+?)\s*\|/', $line, $m)) {
        $modId = trim($m[1]);
        $modId = preg_replace('/\s.*$/', '', $modId);
        $group = trim($m[2]);
        $moduleGroups[$modId] = $group;
    }
}

$planContent = file_get_contents($planFile);
preg_match_all('/@module\s+([A-Z0-9\-]+)(.*?)(?=@module|$)/s', $planContent, $planMatches, PREG_SET_ORDER);
$moduleScreens = [];
foreach ($planMatches as $m) {
    $modId = $m[1];
    if (preg_match('/\*\*SCREENS\*\*(.*?)(\n|$)/s', $m[2], $sm)) {
        $moduleScreens[$modId] = trim($sm[1]);
    }
}

foreach (glob($modulesDir . '/*/manifest.php') as $manifestFile) {
    $modDir = dirname($manifestFile);
    $modId = basename($modDir);
    $manifest = include $manifestFile;
    
    $renders = $manifest['renders'] ?? [];
    if (empty($renders)) continue;
    
    $spFile = $modDir . '/ModuleServiceProvider.php';
    $spContent = file_exists($spFile) ? file_get_contents($spFile) : '';
    
    foreach ($renders as $render) {
        $pattern = "/Livewire::component\(['\"]([^'\"]+)['\"],\s*([A-Za-z0-9_]+)::class\)/";
        if (preg_match_all($pattern, $spContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $alias = $match[1];
                $class = $match[2];
                $renderHyphen = str_replace('_', '-', $render);
                if (str_contains($alias, $renderHyphen) || str_contains(str_replace('-', '_', $alias), $render)) {
                    echo "$modId | $render | $alias | $class | " . ($moduleScreens[$modId] ?? '') . "\n";
                }
            }
        }
    }
}
