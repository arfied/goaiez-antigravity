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
    </div>
</div>
