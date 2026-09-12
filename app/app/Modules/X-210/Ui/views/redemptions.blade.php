<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Offers your customers used</h2>
        @if($redemptions->isEmpty())
            <x-ui.empty-state icon="○" heading="No offers used yet">
                When a customer uses one of your offers, it appears here with how much it took off their bill.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($redemptions as $redemption)
                    <li class="text-ink">
                        {{ $codes[$redemption->promotion_id] ?? 'An offer' }} ·
                        ${{ number_format($redemption->discount_applied_cents / 100, 2) }} off ·
                        {{ $redemption->redeemed_at->toFormattedDateString() }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
