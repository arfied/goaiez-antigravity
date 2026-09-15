<div>
    <div class="your-number-card p-4">
        <h2 class="text-lg font-bold text-ink">Your number</h2>
        @if(!$assignment)
            <p class="text-ink-2">No active number assigned.</p>
        @else
            <p class="font-mono text-xl text-green-600">{{ $number }}</p>
            <p class="text-sm text-green-600">Active</p>
        @endif
    </div>
</div>
