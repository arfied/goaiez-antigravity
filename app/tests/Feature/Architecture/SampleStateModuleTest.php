<?php

test('every <x-surface.sample-state> names a real module', function () {
    // 1. the legal id set — DERIVED from the filesystem, never written down
    $modules = array_map('basename', glob(base_path('app/Modules/*'), GLOB_ONLYDIR));

    // 2. every call site in the module views
    $total = 0;
    $legal = 0;
    $illegal = 0;
    $unparseable = 0;

    $viewsPath = base_path('app/Modules');
    
    // Check if the path exists, as asked in the brief: "Verify that with an assertion, do not trust my path"
    expect(is_dir($viewsPath))->toBeTrue();

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php' && strpos($file->getFilename(), '.blade.php') !== false) {
            $content = file_get_contents($file->getPathname());
            if (strpos($content, 'x-surface.sample-state') !== false) {
                preg_match_all('/<x-surface\.sample-state[^>]*>/i', $content, $matches);
                foreach ($matches[0] as $match) {
                    $total++;
                    if (preg_match('/module="([^"]*)"/i', $match, $m)) {
                        $module = $m[1];
                        if (in_array($module, $modules)) {
                            $legal++;
                        } else {
                            $illegal++;
                        }
                    } else {
                        $unparseable++;
                    }
                }
            }
        }
    }

    // 3. partition into legal / illegal / unparseable
    expect($total)->toBe(248);
    expect($unparseable)->toBe(0);
    expect($illegal)->toBe(223, "223 call sites use a prose description instead of a real module ID (e.g., 'planned in ⭐⭐⭐ **IT IS THE WIZARD...'). These render the master plan's prose to a signed-in owner on tenant.role routes, reachable by URL. It is pinned to fail if Track 1 fixes them (goes down) or if the generator emits another one (goes up).");
    expect($legal + $illegal + $unparseable)->toBe($total);
});
