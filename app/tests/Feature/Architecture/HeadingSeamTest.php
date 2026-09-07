<?php

use Illuminate\Support\Facades\View;

test('owner layout heading seam contract', function () {
    $total = 0;
    $seam = 0;
    $own = 0;
    $unresolvedView = 0;
    $skips = 0;
    $noHeading = 0;

    foreach (glob(base_path('app/Modules/*/Ui/*.php')) as $file) {
        $content = file_get_contents($file);

        if (! str_contains($content, "#[Layout('components.account.layout'")) {
            continue;
        }

        $total++;

        preg_match('/#\[Layout\([^\]]*\]/', $content, $layoutMatch);
        $layoutAttr = $layoutMatch[0] ?? '';
        if (! $layoutAttr) {
            preg_match('/#\[Layout\([^\)]*\)/', $content, $layoutMatch);
            $layoutAttr = $layoutMatch[0] ?? '';
        }

        $hasHeading = preg_match('/[\'"]heading[\'"]\s*=>/', $layoutAttr);

        if ($hasHeading) {
            $seam++;
        } else {
            $own++;
        }

        if (preg_match("/view\(\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
            $viewName = $matches[1];
            if (View::exists($viewName)) {
                $viewPath = View::make($viewName)->getPath();
                $viewContent = file_get_contents($viewPath);

                if (preg_match('/<h([1-6])/', $viewContent, $hMatches)) {
                    $level = $hMatches[1];
                    if ($hasHeading && $level !== '2') {
                        $skips++;
                    } elseif (! $hasHeading && $level !== '1') {
                        $skips++;
                    }
                } else {
                    $noHeading++;
                }
            } else {
                $unresolvedView++;
            }
        } else {
            $unresolvedView++;
        }
    }

    expect($total)->toBe(19, 'If it went UP, a new module component uses the owner layout. If it went DOWN, a component dropped the layout or was deleted.');
    expect($seam)->toBe(18, 'If it went UP, a component added the heading key to its layout. If it went DOWN, a component removed it or was deleted.');
    expect($own)->toBe(1, 'If it went UP, a component uses the layout without the heading key. If it went DOWN, a component added the heading key or was deleted.');
    expect($unresolvedView)->toBe(0, 'If it went UP, a component uses a first view literal that cannot be resolved. Its known edge: a component with two view( literals lands on the first. If it went DOWN, an unresolved view literal was fixed.');
    expect($skips)->toBe(0, 'If it went UP, a blade\'s first <h[1-6] tag is the wrong level (not h2 for seam, not h1 for own). This reads the blade\'s text, not a response body, and never sees a heading emitted by a component such as <x-ui.empty-state heading="…">. If it went DOWN, a blade heading was fixed.');
    expect($noHeading)->toBe(0, 'If it went UP, a resolved view has no <h[1-6] tag at all. This reads the blade\'s text, so a heading emitted by a component (<x-ui.empty-state heading="…"> renders its own <h2>) is not seen, and a $seam member landing in this bucket is not a defect while an $own member is. If it went DOWN, a heading was added or the view was removed.');
});
