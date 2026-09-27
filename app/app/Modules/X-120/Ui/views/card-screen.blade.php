<div>
    <h2 class="text-lg font-bold text-ink">Payment methods</h2>

    @if($error)
        <x-ui.error-panel :heading="$errorHeading ?? 'Could not update your cards'">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    <x-ui.toast kind="success" :message="$success" />

    @foreach($expiringCards as $card)
        <x-ui.attention-card heading="Card Expiring Soon">
            Card ending in {{ $card->last_four }} expires {{ $card->exp_month }}/{{ $card->exp_year }}
        </x-ui.attention-card>
    @endforeach

    <div class="mt-4">
        @if($cards->isEmpty())
            <x-ui.empty-state heading="No cards on file" action="Add Card" target="addCard">
                No card has ever been kept on this account. Keeping one waits on Stripe tokenisation, which is not built in this checkout: the form below checks a number, the expiry and the name, and keeps none of them.
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
                            <x-ui.button wire:click="makeDefault({{ $card->id }})" wire:loading.attr="disabled" wire:target="makeDefault({{ $card->id }})">Make Default</x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">
        @if($waiting)
            <x-ui.attention-card state="attention" heading="Waiting on Stripe">{{ $waiting }}</x-ui.attention-card>
        @endif
        @if($adding)
            <form wire:submit="present" class="flex flex-col gap-2 max-w-sm">
                <p class="text-sm text-ink-2">The number reaches this app once so it can be checked, is never stored, and is never sent on to anyone until Stripe returns a token. We ask for the number, the expiry and the name — nothing else, ever.</p>
                <input type="text" wire:model="number" inputmode="numeric" autocomplete="cc-number" placeholder="Card number" class="border rounded px-2 py-1">
                <div class="flex gap-2">
                    <input type="text" wire:model="expMonth" inputmode="numeric" autocomplete="cc-exp-month" placeholder="MM" class="border rounded px-2 py-1 w-16">
                    <input type="text" wire:model="expYear" inputmode="numeric" autocomplete="cc-exp-year" placeholder="YYYY" class="border rounded px-2 py-1 w-24">
                </div>
                <input type="text" wire:model="name" autocomplete="cc-name" placeholder="Name on the card" class="border rounded px-2 py-1">
                <x-ui.submit target="present" busy="Checking…">Check this card</x-ui.submit>
            </form>
        @else
            <x-ui.button wire:click="addCard">Add a card</x-ui.button>
        @endif
    </div>
</div>
