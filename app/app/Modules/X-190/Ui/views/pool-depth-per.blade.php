<div>
    <div class="pool-depth-per-view p-4">
        <h2 class="text-lg font-bold text-ink">Pool depth</h2>
        @if($count === 0)
            <p class="text-ink-2">No partners in your pool yet.</p>
        @else
            <p class="text-ink">{{ $count }} {{ $count === 1 ? 'partner' : 'partners' }} in your pool.</p>
        @endif
    </div>
</div>
