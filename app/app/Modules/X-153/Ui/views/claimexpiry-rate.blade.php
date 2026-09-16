<div>
    <div class="claim-expiry-container p-4">
        <h2 class="text-lg font-bold text-ink">Claim expiry</h2>
        <p class="text-ink-2">Claims release after 30 minutes. {{ $active }} active · {{ $expired }} expired · {{ $rate }}% expiry rate.</p>
        @if($claims->isEmpty())
            <p class="text-ink-2">No claims yet.</p>
        @else
            <ul>
                @foreach($claims as $c)
                    <li>Alert #{{ $c->alert_id }} claimed by user #{{ $c->claimed_by_user_id }} — {{ $c->status }}@if($c->expires_at) (expires {{ $c->expires_at->format('Y-m-d H:i') }})@endif</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
