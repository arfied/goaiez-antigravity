<div>
    <div class="earnings-view p-4">
        <h2 class="text-lg font-bold text-ink">Affiliate earnings</h2>
        @if($attributions->isEmpty())
            <p class="text-ink-2">No commissions earned yet.</p>
        @else
            <ul>
                @foreach($attributions as $a)
                    <li>{{ $a->order_id }}: {{ $a->commission_cents }} cents on {{ $a->sale_amount_cents }} [{{ $a->clawback_status }}]</li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 p-4 bg-surface border rounded">
            <h3 class="text-md font-bold text-ink">Attribute Sale</h3>
            @if($success)
                <div class="mb-4 text-ink">{{ $success }}</div>
            @endif
            @if($error)
                <div class="mb-4 text-ink-2">{{ $error }}</div>
            @endif
            <form wire:submit="attributeSale" class="flex flex-col gap-2 mt-4">
                <input type="text" wire:model="affiliateCode" placeholder="Affiliate Code" class="border rounded p-2 text-ink bg-paper">
                <input type="text" wire:model="orderId" placeholder="Order ID" class="border rounded p-2 text-ink bg-paper">
                <input type="number" wire:model="saleAmountCents" placeholder="Sale Amount (cents)" class="border rounded p-2 text-ink bg-paper">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Attribute</button>
            </form>
        </div>
    </div>
</div>
