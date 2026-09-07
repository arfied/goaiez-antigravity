<?php

use Illuminate\Support\Facades\View;

test('owner layout heading seam contract', function () {
    $total = 0;
    $seam = 0;
    $own = 0;
    $unresolvedView = 0;
    $skips = 0;
    $noHeading = 0;
    $levelSkips = 0;
    $conditionalHeadings = 0;
    $inlineConditionalHeadings = 0;

    $directives = [];

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

                $depth = 0;
                $viewHasConditionalHeading = false;
                $viewHasInlineConditionalHeading = false;
                foreach (explode("\n", $viewContent) as $line) {
                    $lineHasHeading = preg_match('/<h[1-6]/', $line);
                    if ($lineHasHeading && $depth >= 1) {
                        $viewHasConditionalHeading = true;
                    }
                    if ($lineHasHeading && (str_contains($line, '@if') || str_contains($line, '@unless') || str_contains($line, '@endif') || str_contains($line, '@endunless'))) {
                        $viewHasInlineConditionalHeading = true;
                    }
                    $depth += substr_count($line, '@if');
                    $depth += substr_count($line, '@unless');
                    $depth -= substr_count($line, '@endif');
                    $depth -= substr_count($line, '@endunless');
                }
                if ($viewHasConditionalHeading) {
                    $conditionalHeadings++;
                }
                if ($viewHasInlineConditionalHeading) {
                    $inlineConditionalHeadings++;
                }

                preg_match_all('/@([a-zA-Z]+)/', $viewContent, $dMatches);
                foreach ($dMatches[1] as $name) {
                    $directives[$name] = true;
                }

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

                if (preg_match_all('/<h([1-6])/', $viewContent, $allMatches)) {
                    $sequence = [];
                    if ($hasHeading) {
                        $sequence[] = 1;
                    }
                    foreach ($allMatches[1] as $match) {
                        $sequence[] = (int) $match;
                    }

                    $hasLevelSkip = false;
                    for ($i = 1; $i < count($sequence); $i++) {
                        $prev = $sequence[$i - 1];
                        $next = $sequence[$i];
                        if ($next > $prev + 1) {
                            $hasLevelSkip = true;
                            break;
                        }
                    }

                    if ($hasLevelSkip) {
                        $levelSkips++;
                    }
                }
            } else {
                $unresolvedView++;
            }
        } else {
            $unresolvedView++;
        }
    }

    $untrackedOpeners = [];
    foreach (array_keys($directives) as $name) {
        if (isset($directives['end'.$name]) && $name !== 'if' && $name !== 'unless') {
            $untrackedOpeners[] = $name;
        }
    }
    sort($untrackedOpeners);

    expect($total)->toBe(19, 'If it went UP, a new module component uses the owner layout. If it went DOWN, a component dropped the layout or was deleted.');
    expect($seam)->toBe(18, 'If it went UP, a component added the heading key to its layout. If it went DOWN, a component removed it or was deleted.');
    expect($own)->toBe(1, 'If it went UP, a component uses the layout without the heading key. If it went DOWN, a component added the heading key or was deleted.');
    expect($unresolvedView)->toBe(0, 'If it went UP, a component uses a first view literal that cannot be resolved. Its known edge: a component with two view( literals lands on the first. If it went DOWN, an unresolved view literal was fixed.');
    expect($skips)->toBe(0, 'If it went UP, a blade\'s first <h[1-6] tag is the wrong level (not h2 for seam, not h1 for own). This reads the blade\'s text, not a response body, and never sees a heading emitted by a component such as <x-ui.empty-state heading="…">. If it went DOWN, a blade heading was fixed.');
    expect($noHeading)->toBe(0, 'If it went UP, a resolved view has no <h[1-6] tag at all. This reads the blade\'s text, so a heading emitted by a component (<x-ui.empty-state heading="…"> renders its own <h2>) is not seen, and a $seam member landing in this bucket is not a defect while an $own member is. If it went DOWN, a heading was added or the view was removed.');
    expect($levelSkips)->toBe(0, 'If it went UP, a view\'s heading sequence descends by more than one level. This reads the blade\'s text, so a heading emitted by a component is not in the sequence. It is a count of views, not of bad steps. The <h1> prepended for a $seam member is an ASSUMPTION this code makes about the layout, not something it measures. The sequence is the blade\'s text in document order, so headings in mutually exclusive @if/@elseif/@else arms are concatenated into a sequence no rendered page emits — which can both flag a skip that never renders and hide one that does. If it went DOWN, a view\'s heading sequence was fixed.');
    expect($conditionalHeadings)->toBe(8, 'This is a count of views, not headings (once per view), containing at least one <h[1-6] tag at an @if or @unless nesting depth >= 1 (the depth arithmetic tracks @if and @unless only, and the size of what it does not track is pinned below). Because it counts single-heading views, which cannot skip anything, and nested conditionals, whose headings do co-render in document order — so it is an upper bound on how many views the text-order assumption could be wrong about, not a count of views it is wrong about. If it went UP, a view gained a heading inside a conditional and the blind spot grew. If it went DOWN, a heading moved out of a conditional, or a view left the population. Neither is by itself a defect — it is the size of a known limit, and the response to a move is to re-read whether the arms it counts are mutually exclusive, not to edit a view.');
    expect($inlineConditionalHeadings)->toBe(0, 'This counts views containing at least one line with both a <h[1-6] tag and one of the four directives the depth arithmetic tracks (@if, @unless, @endif, @endunless). If it went UP, a view gained a heading sharing a line with a conditional; if it went DOWN, one left. Neither is by itself a defect — it is the size of a known limit. Limit: it reads the blade\'s text line by line and cannot tell a heading nested inside the conditional from one merely typed beside it.');
    expect(implode(', ', $untrackedOpeners))->toBe('foreach, php', 'This counts distinct directive names in these views for which an @end<name> also occurs, other than the two the depth arithmetic tracks. If it went UP, the population gained a block construct; if it went DOWN, one left. Neither direction is by itself a defect. Instead, ask whether the name that arrived or left creates mutually exclusive arms. Only such a member can make conditionalHeadings stop being an upper bound. A block construct that always emits its body in document order changes nothing about the bound. The response is never to edit a view. Limit: it sees only a block whose closer follows the @end<name> convention and appears in this same population; a @section closed by @stop, or an opener whose closer lives in another file, is invisible to it. Found: '.implode(', ', $untrackedOpeners));
});
