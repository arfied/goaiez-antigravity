<div>
    <h2>Checkout</h2>
    <p>The card itself is tokenised by Stripe on the card screen and the money is captured by the gateway; until those contracts land, this block records the authorisation and the order, and stock comes off the moment the order is placed, not when it is paid.</p>

    @if($error)
        <x-ui.error-panel :heading="$errorHeading ?? 'Could not take that payment'">{{ $error }}</x-ui.error-panel>
    @endif
    
    @if($authorised)
        <x-ui.attention-card state="attention" heading="Nothing authorised yet">{{ $authorised }}</x-ui.attention-card>
    @endif
    
    <x-ui.toast kind="success" :message="$success" />
    
    @if($waiting)
        <x-ui.status-pill state="unknown" :label="$waiting" />
    @endif
    
    <div wire:loading><x-ui.skeleton label="Reading the cart…" /></div>

    <h3>To pay</h3>
    @if($expired)
        <x-ui.attention-card state="attention" heading="This cart expired">Nothing was charged and no stock moved.</x-ui.attention-card>
    @elseif(count($lines) === 0 && $unlistedCount === 0)
        <x-ui.empty-state heading="Nothing to pay for yet.">Add something in the cart block. The cart itself lasts 15 minutes; nothing is held for you until the order is placed here.</x-ui.empty-state>
    @else
        <ul>
            @foreach($lines as $line)
                <li>
                    <span>{{ $line['sellable']->name }}</span>,
                    <span>{{ $line['quantity'] }} × {{ number_format($line['sellable']->unit_price_cents / 100, 2) }} = {{ number_format($line['subtotal_cents'] / 100, 2) }}</span>
                </li>
            @endforeach
        </ul>
        @if($unlistedCount > 0)
            <x-ui.attention-card state="attention" heading="Not every line is shown">
                Lines not shown: {{ $unlistedCount }}. An item that has left the catalogue cannot be
                listed, and the total below still includes it.
            </x-ui.attention-card>
        @endif
        <p class="tabular-nums">Total: {{ number_format($cart->total_cents / 100, 2) }}</p>
        <p>This cart expires at {{ $cart->expires_at->format('H:i:s') }} — nothing is held for you until the order is placed.</p>
        <x-ui.button size="default" variant="secondary" wire:click="authorise" wire:loading.attr="disabled" wire:target="authorise">Authorise this charge</x-ui.button>
        <x-ui.button size="default" wire:click="pay" :disabled="$authToken === null" wire:loading.attr="disabled" wire:target="pay">Pay {{ number_format($cart->total_cents / 100, 2) }}</x-ui.button>
    @endif

    <h3>Orders</h3>
    @if(count($orders) === 0)
        <x-ui.empty-state heading="No orders yet.">An order appears here when a cart is checked out. Nothing in this checkout writes the catalogue a cart is built from, so no order can be placed yet.</x-ui.empty-state>
    @else
        @if($ordersTruncated)
            <p class="text-sm text-ink-2">The 10 most recent orders are shown. Older orders are not on this page.</p>
        @endif
        <ul>
            @foreach($orders as $o)
                <li>
                    <span>{{ $o->order_number }}</span>,
                    <span>{{ number_format($o->total_cents / 100, 2) }}</span>
                    <x-ui.status-pill :state="$orderStatusPillStates[$o->status] ?? 'unknown'" :label="$orderStatusLabels[$o->status] ?? $o->status" />
                    @if($o->status === 'paid' || $o->status === 'pending_payment')
                        <x-ui.button size="default" variant="secondary" wire:click="cancel({{ $o->id }})" wire:loading.attr="disabled" wire:target="cancel({{ $o->id }})">Cancel</x-ui.button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
