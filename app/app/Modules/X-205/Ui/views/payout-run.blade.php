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
    </div>
</div>
