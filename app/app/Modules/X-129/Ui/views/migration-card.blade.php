<div>
    <div class="migration-card-view p-4">
        <h2 class="text-lg font-bold text-ink">Migration status</h2>
        @if($total === 0)
            <p class="text-ink-2">No redirects mapped yet.</p>
        @else
            <p class="text-ink">Verified redirects: {{ $verified }} of {{ $total }}</p>
        @endif
    </div>
</div>
