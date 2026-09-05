<div>
    <x-surface.sample-state module="receives `chargeback.received` from X-198 for **any gateway**" screen="dispute_card" />
    <div class="dispute-card-view p-4">
        <h3 class="text-lg font-bold">Dispute & Chargeback Defense Cards</h3>
        @if($disputes->isEmpty())
            <p class="text-gray-500">No active chargeback disputes.</p>
        @else
            <ul>
                @foreach($disputes as $d)
                    <li>#{{ $d->id }}: Invoice #{{ $d->invoice_id }} - ${{ number_format($d->chargeback_amount_cents / 100, 2) }} [{{ $d->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
