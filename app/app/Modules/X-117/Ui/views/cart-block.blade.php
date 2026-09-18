<div>
<h2>Cart</h2>
<p class="text-base text-ink-2">Prices come from this catalogue and are set here; nothing is charged on this screen, and stock comes off when the order is placed at checkout, not when it is paid.</p>
@if($error) <x-ui.error-panel heading="We couldn't do that">{{ $error }}</x-ui.error-panel> @endif
@if($waiting) <x-ui.attention-card state="attention" heading="Waiting on checkout">{{ $waiting }}</x-ui.attention-card> @endif
@if($success) <p>{{ $success }}</p> @endif
<div wire:loading><x-ui.skeleton label="Reading the cart…" /></div>
<h3>What's on offer</h3>
@if($sellables->isEmpty())
<x-ui.empty-state heading="Nothing on offer yet.">No product or service has been put on this catalogue. Bringing prices across from the pricebook is not built here, so the list fills only once a catalogue row exists.</x-ui.empty-state>
@else
<ul class="space-y-2">
@foreach($sellables as $s)
<li class="border rounded p-4 shadow bg-white">
<span class="font-semibold">{{ $s->name }}</span>
<span class="tabular-nums">{{ number_format($s->unit_price_cents / 100, 2) }}</span>
<span class="text-sm text-ink-2">{{ $s->fulfilment_type }}</span>
<x-ui.status-pill :state="$s->inventory_quantity > 0 ? 'ok' : 'attention'" :label="$s->inventory_quantity > 0 ? $s->inventory_quantity.' in stock' : 'sold out'" />
@if($s->inventory_quantity > 0)
<x-ui.button size="default" wire:click="add({{ $s->id }})" wire:loading.attr="disabled" wire:target="add({{ $s->id }})">Add</x-ui.button>
@endif
</li>
@endforeach
</ul>
@endif
<h3>In the cart</h3>
@if($expired)
<x-ui.attention-card state="attention" heading="This cart expired">The 15 minutes ran out; add again to start a new one. Nothing was charged and no stock moved.</x-ui.attention-card>
@elseif(empty($lines))
<x-ui.empty-state heading="Nothing in the cart yet.">Add a service or a product from the list above. The cart itself lasts 15 minutes; nothing is held for you until the order is placed at checkout.</x-ui.empty-state>
@else
<ul class="space-y-2">
@foreach($lines as $line)
<li class="border rounded p-4 shadow bg-white">
<span class="font-semibold">{{ $line['sellable']->name }}</span>
<span class="tabular-nums">{{ $line['quantity'] }} × {{ number_format($line['sellable']->unit_price_cents / 100, 2) }} = {{ number_format($line['subtotal_cents'] / 100, 2) }}</span>
<x-ui.button size="default" variant="secondary" wire:click="remove({{ $line['sellable']->id }})" wire:loading.attr="disabled" wire:target="remove({{ $line['sellable']->id }})">Remove</x-ui.button>
</li>
@endforeach
</ul>
<p class="tabular-nums">Cart total: {{ number_format($cart->total_cents / 100, 2) }}</p>
<p class="text-sm text-ink-2">This cart expires at {{ $cart->expires_at->format('H:i:s') }} — the clock is the row's, it does not restart on refresh. Nothing is held for you: stock comes off when the order is placed at checkout, and another cart can take the last one first.</p>
<x-ui.button size="default" wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout">Check out</x-ui.button>
@endif
</div>
