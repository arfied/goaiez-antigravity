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
        $layout = 'agency';
    } elseif (str_contains($screensLine, 'tech/mobile:') && !str_contains($screensLine, 'tech/mobile: none')) {
        $layout = 'tech';
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
                    
                    // Found the class, let's look for the PHP file
                    // We need to resolve alias
                    $fqcn = null;
                    if (preg_match('/use\s+([^;]+?)\s+as\s+' . $matchClass . '\s*;/i', $spContent, $useMatch)) {
                        $fqcn = '\\' . trim($useMatch[1]);
                    } else {
                        preg_match_all('/use\s+([^;]+?)\s*;/i', $spContent, $uses);
                        foreach ($uses[1] as $use) {
                            if (preg_match('/as\s+' . $matchClass . '$/i', $use) || preg_match('/\\\\(' . $matchClass . ')$/i', $use)) {
                                $useParts = preg_split('/\s+as\s+/i', $use);
                                $fqcn = '\\' . trim($useParts[0]);
                                break;
                            }
                        }
                    }
                    if ($fqcn) {
                        $fqcn = ltrim($fqcn, '\\');
                        $phpFile = __DIR__.'/../app/app/' . str_replace(['App\\', '\\'], ['', '/'], $fqcn) . '.php';
                        if (file_exists($phpFile)) {
                            $phpContent = file_get_contents($phpFile);
                            if (!str_contains($phpContent, '#[Layout')) {
                                $phpContent = preg_replace(
                                    '/use Livewire\\\\Component;/',
                                    "use Livewire\Component;\nuse Livewire\Attributes\Layout;",
                                    $phpContent
                                );
                                $phpContent = preg_replace(
                                    '/class ' . $matchClass . ' extends Component/',
                                    "#[Layout('components.layouts.$layout')]\nclass " . $matchClass . " extends Component",
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
}
