<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Drafts written for you</h2>
        @if($drafts->isEmpty())
            <x-ui.empty-state icon="○" heading="No drafts yet">
                When a draft is written for you, it appears here with whether it was published.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($drafts as $draft)
                    <li class="text-ink">{{ $draft->title }} · {{ $draft->is_published ? 'Published' : ($draft->gateResults->isEmpty() ? 'Not checked yet' : 'Held back') }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
