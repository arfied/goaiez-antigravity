<div>
    <div class="affiliate-portal-view p-4">
        <h2 class="text-lg font-bold text-ink">Affiliates</h2>
        @if($affiliates->isEmpty())
            <p class="text-ink-2">No affiliates yet.</p>
        @else
            <ul>
                @foreach($affiliates as $af)
                    <li>{{ $af->partner_name }} ({{ $af->affiliate_code }}): {{ $af->commission_rate_bps }} bps · balance {{ $af->current_balance_cents }} cents · lifetime {{ $af->lifetime_earnings_cents }} cents</li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 p-4 bg-surface border rounded">
            <h3 class="text-md font-bold text-ink">Create Affiliate</h3>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            <form wire:submit="createAffiliate" class="flex flex-col gap-2 mt-4">
                <input type="text" wire:model="affiliateCode" placeholder="Affiliate Code" class="border rounded p-2 text-ink bg-paper">
                <input type="text" wire:model="partnerName" placeholder="Partner Name" class="border rounded p-2 text-ink bg-paper">
                <input type="number" wire:model="commissionRateBps" placeholder="Commission Rate (bps)" class="border rounded p-2 text-ink bg-paper">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Create</button>
            </form>
        </div>
    </div>
</div>
