<div>
    <h1 class="text-xl font-semibold mb-4">Payment Methods</h1>

    @if($error)
        <x-ui.error-panel heading="We couldn't update your cards">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    @if($success)
        <p class="text-green-600 mb-4">{{ $success }}</p>
    @endif

    @foreach($expiringCards as $card)
        <x-ui.attention-card heading="Card Expiring Soon">
            Card ending in {{ $card->last_four }} expires {{ $card->exp_month }}/{{ $card->exp_year }}
        </x-ui.attention-card>
    @endforeach

    <div class="mt-4">
        @if($cards->isEmpty())
            <x-ui.empty-state heading="No cards on file" action="Add Card" target="addCard">
                Please add a card.
            </x-ui.empty-state>
        @else
            <ul class="space-y-2">
                @foreach($cards as $card)
                    <li class="p-4 border rounded shadow flex justify-between items-center">
                        <div>
                            Card ending in {{ $card->last_four }} ({{ $card->exp_month }}/{{ $card->exp_year }})
                            @if($card->is_default)
                                <x-ui.status-pill state="ok" label="Default" />
                            @endif
                        </div>
                        @if(!$card->is_default)
                            <x-ui.button wire:click="makeDefault({{ $card->id }})">Make Default</x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">
        @if($status === 'waiting on Stripe tokenisation')
            <x-ui.status-pill state="attention" label="waiting on Stripe tokenisation" />
        @else
            <x-ui.button wire:click="addCard">Add a card</x-ui.button>
        @endif
    </div>
</div>
