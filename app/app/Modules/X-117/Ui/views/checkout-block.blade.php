<div>
    <h1>Checkout</h1>
    <p>The card itself is tokenised by Stripe on the card screen and the money is captured by the gateway; until those contracts land, this block records the authorisation and the order, and stock comes off the moment the order is placed, not when it is paid.</p>

    @if($error)
        <x-ui.error-panel heading="We couldn't take that payment">{{ $error }}</x-ui.error-panel>
    @endif
    
    @if($authorised)
        <x-ui.attention-card state="ok" heading="Authorised">{{ $authorised }}</x-ui.attention-card>
    @endif
    
    @if($success)
        <p>{{ $success }}</p>
    @endif
    
    @if($waiting)
        <x-ui.status-pill state="unknown" :label="$waiting" />
    @endif
    
    <div wire:loading><x-ui.skeleton label="Reading the cart…" /></div>

    <h2>To pay</h2>
    @if($expired)
        <x-ui.attention-card state="attention" heading="This cart expired">Nothing was charged and no stock moved.</x-ui.attention-card>
    @elseif(count($lines) === 0)
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
        <p class="tabular-nums">Total: {{ number_format($cart->total_cents / 100, 2) }}</p>
        <p>This cart expires at {{ $cart->expires_at->format('H:i:s') }} — nothing is held for you until the order is placed.</p>
        <x-ui.button size="default" wire:click="authorise">Authorise this charge</x-ui.button>
        <x-ui.button size="default" wire:click="pay" :disabled="$authToken === null">Pay {{ number_format($cart->total_cents / 100, 2) }}</x-ui.button>
    @endif

    <h2>Orders</h2>
    @if(count($orders) === 0)
        <x-ui.empty-state heading="No orders yet." />
    @else
        <ul>
            @foreach($orders as $o)
                <li>
                    <span>{{ $o->order_number }}</span>,
                    <span>{{ number_format($o->total_cents / 100, 2) }}</span>
                    <x-ui.status-pill :state="$o->status === 'paid' ? 'ok' : 'attention'" :label="$o->status" />
                    @if($o->status === 'paid' || $o->status === 'pending_payment')
                        <x-ui.button size="default" variant="secondary" wire:click="cancel({{ $o->id }})">Cancel</x-ui.button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
