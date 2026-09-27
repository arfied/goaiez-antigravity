<div>
    <div class="payout-run-view p-4">
        <h2 class="text-lg font-bold text-ink">Affiliate payouts</h2>
        @if($payouts->isEmpty())
            <x-ui.empty-state heading="No payouts yet.">A payout is proposed here when an affiliate's balance is ready; proposing one moves no money.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($payouts as $p)
                    <li class="py-2 tabular-nums">affiliate #{{ $p->affiliate_id }} {{ number_format($p->amount_cents / 100, 2) }} {{ $p->status }} <span class="text-sm text-ink-2">{{ $p->money_moved ? 'money moved' : 'no money moved' }}</span></li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 p-4 bg-surface border rounded">
            <h3 class="text-md font-bold text-ink">Request Payout</h3>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            <form wire:submit="requestPayout" class="flex flex-col gap-2 mt-4">
                <input type="number" wire:model="affiliateId" placeholder="Affiliate ID" class="border rounded p-2 text-ink bg-paper">
                <input type="number" wire:model="amountCents" placeholder="Amount (cents)" class="border rounded p-2 text-ink bg-paper">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Request</button>
            </form>
        </div>
    </div>
</div>
