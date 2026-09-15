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

    // A wrong base_path() root would otherwise let the entire test walk nothing, partition nothing and pass.
    expect(is_dir($viewsPath))->toBeTrue();

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php' && strpos($file->getFilename(), '.blade.php') !== false) {
            $content = file_get_contents($file->getPathname());
            $total += substr_count($content, '<x-surface.sample-state');
            if (strpos($content, 'x-surface.sample-state') !== false) {
                preg_match_all('/<x-surface\.sample-state[^>]*>/i', $content, $matches);
                foreach ($matches[0] as $match) {
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
    expect($total)->toBe(155);
    expect($legal)->toBe(25, '21 call sites are in known module blade files; they have been converted to x-123.module-name by Track 1. The test prevents them turning back into prose.');
    expect($unparseable)->toBe(0, 'If it went up, a call to sampleState has an unrecognizable key format. Find it and use either the module syntax or the legacy text syntax.');
    expect($illegal)->toBe(130, '219 call sites use a prose description instead of a real module ID (e.g., \'planned in ⭐⭐⭐ **IT IS THE WIZARD...\'). A red means the number moved: up when the generator emits another, down when Track 1 repairs them, and "lower the number and record it" is the honest response to the second. The limit of what was measured: these are call sites in app/app/Modules/**/*.blade.php; no blade has been mapped to a route.');
    expect($legal + $illegal + $unparseable)->toBe($total);
});
