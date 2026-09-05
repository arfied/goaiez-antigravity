<div>
    <x-surface.sample-state module="C-Reviews" screen="tickets" />
    <div class="tickets-container p-4">
        <h3 class="text-lg font-bold">Low-Rating Triage Tickets</h3>
        @if($tickets->isEmpty())
            <p class="text-gray-500">No open low-rating tickets.</p>
        @else
            <ul>
                @foreach($tickets as $t)
                    <li>Review #{{ $t->id }} ({{ $t->rating }}★): {{ $t->review_text }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
