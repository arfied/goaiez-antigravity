<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Drafts held back before publishing</h2>
        @if($rejections->isEmpty())
            <x-ui.empty-state icon="○" heading="Nothing held back">
                When a draft is held back before publishing, it appears here with the reason.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($rejections as $rejection)
                    <li class="text-ink">{{ $titles[$rejection->draft_id] }} · {{ preg_replace('/^R\d+:\s*/', '', (string) $rejection->rejection_reason) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
