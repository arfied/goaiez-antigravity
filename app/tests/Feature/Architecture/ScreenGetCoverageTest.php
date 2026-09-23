<?php

test('every owner-layout module screen is measured for a real GET test; the uncovered list is pinned (E)', function () {
    $population = [];
    $unrouted = [];
    $covered = [];
    $uncovered = [];

    foreach (glob(base_path('app/Modules/*/Ui/*.php')) as $file) {
        $content = file_get_contents($file);
        if (! str_contains($content, "#[Layout('components.account.layout'")) {
            continue;
        }

        preg_match('#/Modules/([^/]+)/Ui/([^.]+)\.php#', $file, $m);
        $module = $m[1];
        $class = $m[2];

        $population[] = "$module $class";

        $routesPath1 = base_path("app/Modules/{$module}/routes.generated.php");
        $routesPath2 = base_path("app/Modules/{$module}/ModuleServiceProvider.php");
        $routeNames = [];
        $routePaths = [];

        $searchFiles = [];
        if (file_exists($routesPath1)) {
            $searchFiles[] = $routesPath1;
        }
        if (file_exists($routesPath2)) {
            $searchFiles[] = $routesPath2;
        }

        $hasRoute = false;
        foreach ($searchFiles as $sf) {
            $sfContent = file_get_contents($sf);
            $lines = explode("\n", $sfContent);
            foreach ($lines as $line) {
                if (str_contains($line, 'Route::get(') && str_contains($line, $class)) {
                    $hasRoute = true;
                    if (preg_match("/->name\(['\"]([^'\"]+)['\"]\)/", $line, $nm)) {
                        $routeNames[] = $nm[1];
                    }
                    if (preg_match("/Route::get\(['\"]([^'\"]+)['\"]/", $line, $pm)) {
                        $routePaths[] = $pm[1];
                    }
                }
            }
        }

        if (! $hasRoute) {
            $unrouted[] = "$module $class";

            continue;
        }

        $isCovered = false;
        $testsPath = base_path("tests/Modules/{$module}");
        if (is_dir($testsPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsPath));
            foreach ($iterator as $testFile) {
                if ($testFile->isFile() && $testFile->getExtension() === 'php') {
                    $testContent = file_get_contents($testFile->getPathname());
                    if (str_contains($testContent, 'assertOk()')) {
                        foreach ($routeNames as $rn) {
                            if (str_contains($testContent, "route('{$rn}')") ||
                                str_contains($testContent, "route('{$rn}',") ||
                                str_contains($testContent, "route(\"{$rn}\")") ||
                                str_contains($testContent, "route(\"{$rn}\",") ||
                                str_contains($testContent, "route('{$rn}'") // added back to be safe, exact matching isn't strictly requested by "contains route('<that route name>'"
                            ) {
                                $isCovered = true;
                                break;
                            }
                        }
                        if ($isCovered) {
                            break;
                        }
                        foreach ($routePaths as $rp) {
                            $literal = '/'.ltrim($rp, '/');
                            if (str_contains($testContent, "'{$literal}'") || str_contains($testContent, "\"{$literal}\"")) {
                                $isCovered = true;
                                break;
                            }
                        }
                        if ($isCovered) {
                            break;
                        }
                    }
                }
            }
        }

        if ($isCovered) {
            $covered[] = "$module $class";
        } else {
            $routeDisp = ! empty($routeNames) ? $routeNames[0] : (! empty($routePaths) ? $routePaths[0] : 'unknown');
            $uncovered[] = "$module $class -> $routeDisp";
        }
    }

    $cPop = count($population);
    $cCov = count($covered);
    $cUncov = count($uncovered);
    $cUnrou = count($unrouted);

    expect($cCov + $cUncov + $cUnrou)->toBe($cPop);

    if ($cUncov > 0) {
        $msg = "Uncovered screens:\n".implode("\n", $uncovered)."\nIf it went UP, pin the new number here. If it went DOWN, lower the number and record it.";
        expect($cUncov)->toBe(0, $msg);
    } else {
        expect($cUncov)->toBe(0, 'If it went UP, pin the new number here. If it went DOWN, lower the number and record it.');
    }

    expect($cCov)->toBe(242, 'If it went UP, pin the new number here. If it went DOWN, lower the number and record it.; 2026-09-23 X-119 TeachingBox added a real GET test; 2026-09-23 X-212 PickSource became a real screen and its prose banner was removed');
    expect($cUnrou)->toBe(0, 'If it went UP, pin the new number here. If it went DOWN, lower the number and record it.');
});
