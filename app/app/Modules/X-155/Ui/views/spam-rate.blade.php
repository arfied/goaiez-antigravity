<div>
    <div class="spam-rate-view p-4">
        <h2 class="text-lg font-bold text-ink">Spam rate</h2>
        @if($total === 0)
            <p class="text-ink-2">No submissions yet.</p>
        @else
            <p>Total: {{ $total }}</p>
            <p>Spam: {{ $spam }}</p>
            <p>Rate: {{ $rate }}%</p>
        @endif
    </div>
</div>
