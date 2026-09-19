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
    $walkedPaths = [];
    $moduleTotals = [];
    $moduleSeams = [];

    foreach (glob(base_path('app/Modules/*/Ui/*.php')) as $file) {
        preg_match('#/Modules/([^/]+)/Ui/#', $file, $m);
        $module = $m[1] ?? 'Unknown';

        $walkedPaths[realpath($file)] = true;
        $content = file_get_contents($file);

        if (! str_contains($content, "#[Layout('components.account.layout'")) {
            continue;
        }

        $total++;
        $moduleTotals[$module] = ($moduleTotals[$module] ?? 0) + 1;

        preg_match('/#\[Layout\([^\]]*\]/', $content, $layoutMatch);
        $layoutAttr = $layoutMatch[0] ?? '';
        if (! $layoutAttr) {
            preg_match('/#\[Layout\([^\)]*\)/', $content, $layoutMatch);
            $layoutAttr = $layoutMatch[0] ?? '';
        }

        $hasHeading = preg_match('/[\'"]heading[\'"]\s*=>/', $layoutAttr);

        if ($hasHeading) {
            $seam++;
            $moduleSeams[$module] = ($moduleSeams[$module] ?? 0) + 1;
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

    $unwalked = 0;
    $unwalkedSeam = 0;
    $unwalkedSkips = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app')));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            if (isset($walkedPaths[realpath($file->getPathname())])) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (str_contains($content, "#[Layout('components.account.layout'")) {
                $unwalked++;

                preg_match('/#\[Layout\([^\]]*\]/', $content, $layoutMatch);
                $layoutAttr = $layoutMatch[0] ?? '';
                if (! $layoutAttr) {
                    preg_match('/#\[Layout\([^\)]*\)/', $content, $layoutMatch);
                    $layoutAttr = $layoutMatch[0] ?? '';
                }

                $hasHeading = preg_match('/[\'"]heading[\'"]\s*=>/', $layoutAttr);

                if ($hasHeading) {
                    $unwalkedSeam++;
                }

                $hasResolvableView = false;
                $viewContent = '';
                if (preg_match("/view\(\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
                    $viewName = $matches[1];
                    if (View::exists($viewName)) {
                        $hasResolvableView = true;
                        $viewPath = View::make($viewName)->getPath();
                        $viewContent = file_get_contents($viewPath);
                    }
                }

                if (! $hasResolvableView) {
                    $unwalkedSkips++;
                } else {
                    if (preg_match('/<h([1-6])/', $viewContent, $hMatches)) {
                        $level = $hMatches[1];
                        if ($hasHeading && $level !== '2') {
                            $unwalkedSkips++;
                        } elseif (! $hasHeading && $level !== '1') {
                            $unwalkedSkips++;
                        }
                    } else {
                        $unwalkedSkips++;
                    }
                }
            }
        }
    }

    $expectedMap = headingSeamCountsByModule();
    foreach ($expectedMap as $module => $counts) {
        $actualTotal = $moduleTotals[$module] ?? 0;
        $actualSeam = $moduleSeams[$module] ?? 0;
        expect($actualTotal)->toBe($counts['total'], "Module {$module} total count. If it went UP, a new module component uses the owner layout. If it went DOWN, a component dropped the layout or was deleted.");
        expect($actualSeam)->toBe($counts['seam'], "Module {$module} seam count. If it went UP, a component added the heading key to its layout. If it went DOWN, a component removed it or was deleted.");
    }

    expect(array_sum(array_column($expectedMap, 'total')))->toBe(204);
    expect(array_sum(array_column($expectedMap, 'seam')))->toBe(203);

    expect($total)->toBe(204, 'If it went UP, a new module component uses the owner layout. If it went DOWN, a component dropped the layout or was deleted.');
    expect($seam)->toBe(203, 'If it went UP, a component added the heading key to its layout. If it went DOWN, a component removed it or was deleted.');
    expect($seam + $own)->toBe($total, 'Seam plus own must equal total.');
    expect($own)->toBe(1, 'If it went UP, a component kept the layout but brought its own heading, violating T140 §2. If it went DOWN, it deleted the override.');
    expect($unresolvedView)->toBe(0, 'If it went UP, a component uses a first view literal that cannot be resolved. Its known edge: a component with two view( literals lands on the first. If it went DOWN, an unresolved view literal was fixed.');
    expect($skips)->toBe(0, 'If it went UP, a blade\'s first <h[1-6] tag is the wrong level (not h2 for seam, not h1 for own). This reads the blade\'s text, not a response body, and never sees a heading emitted by a component such as <x-ui.empty-state heading="…">. If it went DOWN, a blade heading was fixed.');
    expect($noHeading)->toBe(1, 'If it went UP, a resolved view has no <h[1-6] tag at all. This reads the blade\'s text, so a heading emitted by a component (<x-ui.empty-state heading="…"> renders its own <h2>) is not seen, and a $seam member landing in this bucket is not a defect while an $own member is. If it went DOWN, a heading was added or the view was removed.');
    expect($levelSkips)->toBe(1, 'If it went UP, a view\'s heading sequence descends by more than one level. This reads the blade\'s text, so a heading emitted by a component is not in the sequence. It is a count of views, not of bad steps. The <h1> prepended for a $seam member is an ASSUMPTION this code makes about the layout, not something it measures. The sequence is the blade\'s text in document order, so headings in mutually exclusive @if/@elseif/@else arms are concatenated into a sequence no rendered page emits — which can both flag a skip that never renders and hide one that does. If it went DOWN, a view\'s heading sequence was fixed.');
    expect($conditionalHeadings)->toBe(34, 'This is a count of views, not headings (once per view), containing at least one <h[1-6] tag at an @if or @unless nesting depth >= 1 (the depth arithmetic tracks @if and @unless only, and the size of what it does not track is pinned below). Because it counts single-heading views, which cannot skip anything, and nested conditionals, whose headings do co-render in document order — so it is an upper bound on how many views the text-order assumption could be wrong about, not a count of views it is wrong about. If it went UP, a view gained a heading inside a conditional and the blind spot grew. If it went DOWN, a heading moved out of a conditional, or a view left the population. Neither is by itself a defect — it is the size of a known limit, and the response to a move is to re-read whether the arms it counts are mutually exclusive, not to edit a view.');
    expect($inlineConditionalHeadings)->toBe(0, 'This counts views containing at least one line with both a <h[1-6] tag and one of the four directives the depth arithmetic tracks (@if, @unless, @endif, @endunless). If it went UP, a view gained a heading sharing a line with a conditional; if it went DOWN, one left. Neither is by itself a defect — it is the size of a known limit. Limit: it reads the blade\'s text line by line and cannot tell a heading nested inside the conditional from one merely typed beside it.');
    expect(implode(', ', $untrackedOpeners))->toBe('error, foreach, forelse, php', 'This counts distinct directive names in these views for which an @end<name> also occurs, other than the two the depth arithmetic tracks. If it went UP, the population gained a block construct; if it went DOWN, one left. Neither direction is by itself a defect. Instead, ask whether the name that arrived or left creates mutually exclusive arms. Only such a member can make conditionalHeadings stop being an upper bound. A block construct that always emits its body in document order changes nothing about the bound. The response is never to edit a view. Limit: it sees only a block whose closer follows the @end<name> convention and appears in this same population; a @section closed by @stop, or an opener whose closer lives in another file, is invisible to it. Found: '.implode(', ', $untrackedOpeners));
    expect($unwalked)->toBe(26, 'This counts components declaring the owner layout that this test\'s own scope does not walk. If it went UP, a new owner-layout component landed outside the walked scope, so every pin in this file now covers a smaller fraction of the set the file is named for. If it went DOWN, one was deleted, or the scope was widened to reach it. Neither direction is by itself a defect, and the response is to re-read the scope, never to edit a blade or a component. Limit: it matches the #[Layout(\'components.account.layout\')] literal as text, so a component that sets its layout at runtime is invisible to it.');
    expect($unwalkedSeam)->toBe(0, 'If it went UP, an unwalked component started passing a heading, so the layout now emits its <h1> and that component\'s own view should no longer carry one. If it went DOWN, one stopped, or was deleted, or the scope was widened to reach it. Neither direction is by itself a defect, and the response is to re-read which half of the seam contract applies to that component, never to edit a blade or a component. Limit: it matches the heading key as text, so a heading supplied at runtime is invisible to it.');
    expect($unwalkedSkips)->toBe(0, 'If it went UP via (c), a view\'s first heading is the wrong level for its shape; this IS a defect. If it went UP via (a) or (b), a component\'s view stopped resolving, or a view lost its only heading. (a) and (b) are folded in DELIBERATELY, and neither is by itself a defect. If it went DOWN, one of the three was fixed, or a component left the population. Limits: it reads the blade\'s text, so a heading emitted by a component is not seen; and it is a count of components, not of bad headings.');
});
/**
 * Map of expected heading seam counts by module.
 *
 * This map avoids merge conflicts when multiple branches add screens in different modules.
 * A new module's first owner screen adds a line here rather than bumping a global total.
 */
function headingSeamCountsByModule(): array
{
    return [
        'C-Agent' => ['total' => 4, 'seam' => 4],
        'C-Ai' => ['total' => 1, 'seam' => 1],
        'C-Billing' => ['total' => 4, 'seam' => 4],
        'C-Mail' => ['total' => 2, 'seam' => 2],
        'C-Reviews' => ['total' => 4, 'seam' => 4],
        'C-Sms' => ['total' => 4, 'seam' => 4],
        'C-Whatsapp' => ['total' => 2, 'seam' => 2],
        'X-01' => ['total' => 4, 'seam' => 4],
        'X-07' => ['total' => 1, 'seam' => 1],
        'X-08' => ['total' => 3, 'seam' => 3],
        'X-10' => ['total' => 3, 'seam' => 3],
        'X-102' => ['total' => 4, 'seam' => 4],
        'X-104' => ['total' => 2, 'seam' => 2],
        'X-108' => ['total' => 2, 'seam' => 2],
        'X-110' => ['total' => 6, 'seam' => 6],
        'X-112' => ['total' => 2, 'seam' => 2],
        'X-113' => ['total' => 2, 'seam' => 2],
        'X-117' => ['total' => 2, 'seam' => 2],
        'X-118' => ['total' => 5, 'seam' => 5],
        'X-119' => ['total' => 2, 'seam' => 2],
        'X-120' => ['total' => 1, 'seam' => 1],
        'X-121' => ['total' => 1, 'seam' => 1],
        'X-123' => ['total' => 1, 'seam' => 1],
        'X-124' => ['total' => 2, 'seam' => 2],
        'X-125' => ['total' => 3, 'seam' => 3],
        'X-129' => ['total' => 2, 'seam' => 2],
        'X-130' => ['total' => 2, 'seam' => 2],
        'X-131' => ['total' => 1, 'seam' => 1],
        'X-132' => ['total' => 2, 'seam' => 2],
        'X-137' => ['total' => 2, 'seam' => 2],
        'X-138' => ['total' => 2, 'seam' => 2],
        'X-139' => ['total' => 3, 'seam' => 3],
        'X-140' => ['total' => 1, 'seam' => 1],
        'X-142' => ['total' => 2, 'seam' => 2],
        'X-153' => ['total' => 3, 'seam' => 3],
        'X-155' => ['total' => 3, 'seam' => 3],
        'X-156' => ['total' => 2, 'seam' => 2],
        'X-157' => ['total' => 1, 'seam' => 1],
        'X-16' => ['total' => 3, 'seam' => 3],
        'X-160' => ['total' => 2, 'seam' => 2],
        'X-162' => ['total' => 2, 'seam' => 2],
        'X-163' => ['total' => 3, 'seam' => 3],
        'X-164' => ['total' => 1, 'seam' => 1],
        'X-165' => ['total' => 2, 'seam' => 2],
        'X-166' => ['total' => 4, 'seam' => 4],
        'X-167' => ['total' => 2, 'seam' => 2],
        'X-168' => ['total' => 3, 'seam' => 3],
        'X-170' => ['total' => 2, 'seam' => 2],
        'X-173' => ['total' => 2, 'seam' => 2],
        'X-175' => ['total' => 1, 'seam' => 1],
        'X-176' => ['total' => 1, 'seam' => 1],
        'X-177' => ['total' => 2, 'seam' => 2],
        'X-178' => ['total' => 1, 'seam' => 1],
        'X-180' => ['total' => 1, 'seam' => 1],
        'X-181' => ['total' => 3, 'seam' => 3],
        'X-182' => ['total' => 2, 'seam' => 2],
        'X-183' => ['total' => 2, 'seam' => 2],
        'X-184' => ['total' => 2, 'seam' => 2],
        'X-185' => ['total' => 2, 'seam' => 2],
        'X-186' => ['total' => 4, 'seam' => 4],
        'X-188' => ['total' => 4, 'seam' => 4],
        'X-189' => ['total' => 1, 'seam' => 1],
        'X-190' => ['total' => 3, 'seam' => 3],
        'X-191' => ['total' => 2, 'seam' => 2],
        'X-192' => ['total' => 1, 'seam' => 0],
        'X-193' => ['total' => 1, 'seam' => 1],
        'X-194' => ['total' => 2, 'seam' => 2],
        'X-196' => ['total' => 1, 'seam' => 1],
        'X-198' => ['total' => 3, 'seam' => 3],
        'X-199' => ['total' => 5, 'seam' => 5],
        'X-201' => ['total' => 2, 'seam' => 2],
        'X-202' => ['total' => 2, 'seam' => 2],
        'X-203' => ['total' => 3, 'seam' => 3],
        'X-204' => ['total' => 2, 'seam' => 2],
        'X-205' => ['total' => 3, 'seam' => 3],
        'X-206' => ['total' => 3, 'seam' => 3],
        'X-207' => ['total' => 4, 'seam' => 4],
        'X-209' => ['total' => 2, 'seam' => 2],
        'X-210' => ['total' => 5, 'seam' => 5],
        'X-211' => ['total' => 4, 'seam' => 4],
        'X-212' => ['total' => 5, 'seam' => 5],
        'X-218' => ['total' => 2, 'seam' => 2],
        'X-66' => ['total' => 3, 'seam' => 3],
        'X-82' => ['total' => 1, 'seam' => 1],
    ];
}
