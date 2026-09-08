<div>
    <x-surface.sample-state module="assigns a GO AI EZ number at signup" screen="your_number_card" />
    <div class="your-number-card p-4">
        <h3 class="text-lg font-bold">Your Live Business Number</h3>
        @if(!$assignment)
            <p class="text-gray-500">No active number assigned.</p>
        @else
            <p class="font-mono text-xl text-green-600">{{ $number }}</p>
            <p class="text-sm text-green-600">Active</p>
        @endif
    </div>
</div>
