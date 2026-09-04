<div wire:init="load">
    @if($errorMessage)
        <x-ui.error-panel heading="We could not load recommendations." retry="load" />
    @elseif($recs->isNotEmpty())
        <div class="mb-4">
            <h3 class="font-display text-lg font-bold mb-4">Today's Recommendations</h3>
            @foreach($recs as $r)
                <div class="mb-4">
                    <x-ui.attention-card
                        state="attention"
                        heading="{{ $r->title }}"
                    >
                        Action required: {{ $r->action_key }}
                    </x-ui.attention-card>
                    <div class="mt-2 flex gap-2">
                        <x-ui.button wire:click="accept({{ $r->id }})" size="default" variant="primary">Accept</x-ui.button>
                        <x-ui.button wire:click="dismiss({{ $r->id }})" size="default" variant="secondary">Dismiss</x-ui.button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
