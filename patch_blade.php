<?php

$content = file_get_contents('app/app/Modules/X-103/Ui/views/site-build.blade.php');

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

// Add success message right under error
$successMsg = <<<'CODE'
            @if(isset($success) && $success)
                <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
                    <p class="text-green-700">{{ $success }}</p>
                </div>
            @endif
CODE;

$content = str_replace(
    '@endif',
    "@endif\n\n".$successMsg,
    $content
);

// First @endif is for error, wait let's use exact replace
