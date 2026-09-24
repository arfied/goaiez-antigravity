<?php

$content = shell_exec('git show HEAD:app/app/Modules/X-103/Ui/views/site-build.blade.php');

$card = <<<'CODE'
            <!-- STEP 3: Pick a look -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">3. Pick a look</h3>
                <p class="mt-1 text-sm text-ink-2">Three looks for your home page from your industry's starting point, drawn from the words you have now. Pictures show grey here; they are real on the live site. Pick one and every page follows it.</p>
                @if($previews === null)
                    <p class="mt-4 text-sm text-ink-2 italic">Draft the site first — there is nothing to show yet.</p>
                @else
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach(['a' => 'Look A', 'b' => 'Look B', 'c' => 'Look C'] as $k => $label)
                            <div class="rounded border {{ $chosenVariant === $k ? 'border-ink' : 'border-line' }} p-2" wire:key="look-{{ $k }}">
                                <iframe title="{{ $label }} preview" srcdoc="{{ $previews[$k] }}" sandbox="" loading="lazy" class="w-full h-64 bg-white border border-line"></iframe>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-ink">{{ $label }}@if($chosenVariant === $k) — yours @endif</span>
                                    <button wire:click="chooseLook('{{ $k }}')" class="btn btn-secondary text-sm">Pick this</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
CODE;

$content = str_replace(
    '<!-- STEP 3: Publish -->',
    $card."\n\n            <!-- STEP 4: Publish -->",
    $content
);

// We need to renumber 3->4, 4->5, 5->6, 6->7, 7->8
$content = str_replace('<h3 class="text-lg font-medium text-ink">3. Publish</h3>', '<h3 class="text-lg font-medium text-ink">4. Publish</h3>', $content);
$content = str_replace('<!-- STEP 4: Your domain -->', '<!-- STEP 5: Your domain -->', $content);
$content = str_replace('<h3 class="text-lg font-medium text-ink">4. Your domain</h3>', '<h3 class="text-lg font-medium text-ink">5. Your domain</h3>', $content);
$content = str_replace('<!-- STEP 5: This week\'s suggestions -->', '<!-- STEP 6: This week\'s suggestions -->', $content);
$content = str_replace('<h3 class="text-lg font-medium text-ink">5. This week\'s suggestions</h3>', '<h3 class="text-lg font-medium text-ink">6. This week\'s suggestions</h3>', $content);
$content = str_replace('<!-- STEP 6: Learn from the top 5 nearby -->', '<!-- STEP 7: Learn from the top 5 nearby -->', $content);
$content = str_replace('<h3 class="text-lg font-medium text-ink">6. Learn from the top 5 nearby</h3>', '<h3 class="text-lg font-medium text-ink">7. Learn from the top 5 nearby</h3>', $content);
$content = str_replace('<!-- STEP 7: Headline test -->', '<!-- STEP 8: Headline test -->', $content);
$content = str_replace('<h3 class="text-lg font-medium text-ink">7. Try a headline</h3>', '<h3 class="text-lg font-medium text-ink">8. Try a headline</h3>', $content);

$successMsg = <<<'CODE'
            @if(isset($success) && $success)
                <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
                    <p class="text-green-700">{{ $success }}</p>
                </div>
            @endif
CODE;

$content = str_replace(
    "            @endif\n\n            <!-- STEP 1: Crawl -->",
    "            @endif\n\n".$successMsg."\n\n            <!-- STEP 1: Crawl -->",
    $content
);

file_put_contents('app/app/Modules/X-103/Ui/views/site-build.blade.php', $content);
