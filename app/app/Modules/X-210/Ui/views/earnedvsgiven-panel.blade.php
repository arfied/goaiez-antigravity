<div>
    <div class="earnedvsgiven-panel-view p-4">
        <h2 class="text-lg font-bold text-ink">Discounts given</h2>
        @if((int) $totalDiscount === 0)
            <p class="text-ink-2">No discounts given yet.</p>
        @else
            <p class="text-ink">Total discount given: ${{ number_format($totalDiscount / 100, 2) }}</p>
        @endif
    </div>
</div>
