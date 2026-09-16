<div>
    <div class="complaints-board p-4">
        <h2 class="text-lg font-bold text-ink">Number complaints</h2>
        @if($numbers->isEmpty())
            <p class="text-ink-2">All numbers complaint rate below 0.1% threshold.</p>
        @else
            <ul>
                @foreach($numbers as $n)
                    <li>{{ $n->phone_number }}: {{ $n->complaint_count }} complaints [{{ $n->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
