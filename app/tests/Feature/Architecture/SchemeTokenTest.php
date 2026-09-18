<?php

use Illuminate\Support\Facades\View;

test('owner layout views count the fixed neutral colours the colour scheme cannot follow', function () {
    $neutral = 0;
    $unresolved = 0;

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app')));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        if (! str_contains($content, "#[Layout('components.account.layout'")) {
            continue;
        }
        if (preg_match("/view\(\s*['\"]([^'\"]+)['\"]/", $content, $matches) && View::exists($matches[1])) {
            $neutral += preg_match_all(
                '/[a-z:-]*(bg|text|border|divide|ring|placeholder)-(white|black|gray-[0-9]+|slate-[0-9]+|zinc-[0-9]+|neutral-[0-9]+|stone-[0-9]+)/',
                file_get_contents(View::make($matches[1])->getPath())
            );
        } else {
            $unresolved++;
        }
    }

    expect($unresolved)->toBe(0, 'it counts owner-layout components whose first `view(\'…\')` literal is missing or does not resolve, and whose views are therefore not scanned. If it went UP, the neutral count below covers fewer screens than the population, in silence. If it went DOWN, a literal was fixed or the component left the population. Limit: it matches the `#[Layout(\'components.account.layout\'` literal as text, so a component that sets its layout at runtime is invisible to it.');
    expect($neutral)->toBe(6, 'what it counts: utilities naming a fixed neutral (white, black, or a gray, slate, zinc, neutral or stone step) on bg, text, border, divide, ring or placeholder, with any variant prefix, in the own view of every component declaring the owner layout; why it matters: those colours do not follow `prefers-color-scheme` while the paper, canvas, card, rule and ink tokens do, so one of them beside a token can be unreadable in one of the two schemes; UP means such a utility entered an owner view, so check it reads on both surfaces and prefer a token; DOWN means one was replaced by a token or its view left the population; neither direction is by itself a defect, because `text-white` on a saturated button reads in both schemes; its limits: it reads the view\'s text, not a response body, so partials, Blade components and the layout are not counted; and a `dark:` variant counts like any other utility, because it reads class names, not when a variant applies.');
});
