<?php
$planFile = __DIR__.'/../app/GOAIEZ-MASTER-PLAN.md';
$planContent = file_get_contents($planFile);
$planSections = preg_split('/(?=@module\s+[A-Z0-9\-]+)/', $planContent);

$moduleScreens = [];
foreach ($planSections as $section) {
    if (preg_match('/@module\s+([A-Z0-9\-]+)/', $section, $m)) {
        $modId = $m[1];
        if (preg_match('/^\s*\*\*SCREENS\*\*(.*?)$/m', $section, $sm)) {
            $moduleScreens[$modId] = trim($sm[1]);
        }
    }
}

foreach (glob(__DIR__.'/../app/app/Modules/*/manifest.php') as $manifestFile) {
    $modDir = dirname($manifestFile);
    $modId = basename($modDir);
    $manifest = include $manifestFile;
    
    $renders = $manifest['renders'] ?? [];
    if (empty($renders)) continue;
    
    $screensLine = strtolower($moduleScreens[$modId] ?? '');
    
    $layout = null;
    if (str_contains($screensLine, 'agency:') && !str_contains($screensLine, 'agency: none')) {
        $layout = 'components.layouts.agency';
    } elseif (str_contains($screensLine, 'tech/mobile:') && !str_contains($screensLine, 'tech/mobile: none')) {
        $layout = 'components.layouts.tech';
    }
    
    if (!$layout) continue;
    
    $spFile = $modDir . '/ModuleServiceProvider.php';
    $spContent = file_exists($spFile) ? file_get_contents($spFile) : '';
    
    foreach ($renders as $render) {
        $pattern = "/Livewire::component\(['\"]([^'\"]+)['\"],\s*([A-Za-z0-9_]+)::class\)/";
        if (preg_match_all($pattern, $spContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $matchAlias = $match[1];
                $matchClass = $match[2];
                
                $renderHyphen = str_replace('_', '-', $render);
                if (str_contains($matchAlias, $renderHyphen) || str_contains(str_replace('-', '_', $matchAlias), $render)) {
                    // Try to find the file just by traversing the Ui dir
                    $uiDir = $modDir . '/Ui';
                    $phpFile = null;
                    if (file_exists($uiDir . '/' . $matchClass . '.php')) {
                        $phpFile = $uiDir . '/' . $matchClass . '.php';
                    } else {
                        // find recursively
                        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uiDir));
                        foreach ($iterator as $file) {
                            if ($file->getExtension() === 'php' && $file->getBasename('.php') === $matchClass) {
                                $phpFile = $file->getPathname();
                                break;
                            }
                        }
                    }
                    
                    if ($phpFile) {
                        $phpContent = file_get_contents($phpFile);
                        if (!str_contains($phpContent, '#[Layout')) {
                            $phpContent = preg_replace(
                                '/use Livewire\\\\Component;/',
                                "use Livewire\Component;\nuse Livewire\Attributes\Layout;",
                                $phpContent
                            );
                            $phpContent = preg_replace(
                                '/class ' . $matchClass . '/',
                                "#[Layout('$layout')]\nclass " . $matchClass,
                                $phpContent
                            );
                            file_put_contents($phpFile, $phpContent);
                            echo "Updated $phpFile to use layout $layout\n";
                        }
                    }
                }
            }
        }
    }
}
