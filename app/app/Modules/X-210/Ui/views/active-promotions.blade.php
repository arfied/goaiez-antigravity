<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Offers running now</h2>
        @if($promotions->isEmpty())
            <x-ui.empty-state icon="○" heading="No offers running">
                When you create an offer for your customers, it appears here while it can still be used.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($promotions as $promotion)
                    <li class="text-ink">
                        {{ $promotion->code }} ·
                        @if($promotion->discount_type === 'percentage')
                            {{ $promotion->discount_value }}% off
                        @else
                            ${{ number_format($promotion->discount_value / 100, 2) }} off
                        @endif
                        · {{ $promotion->redemptions_count }} of {{ $promotion->max_redemptions }} used
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
