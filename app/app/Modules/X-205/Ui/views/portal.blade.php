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
    </div>
</div>
