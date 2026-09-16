<div>
    <div class="groundcheck-container p-4">
        <h2 class="text-lg font-bold text-ink">Agent grounding</h2>
        <p class="text-ink-2">{{ $answered }} answered · {{ $refused }} refused · {{ $handoff }} handed off</p>
        @if($turns->isEmpty())
            <p class="text-ink-2">No agent turns yet.</p>
        @else
            <ul>
                @foreach($turns as $turn)
                    <li>Turn {{ $turn->turn_number }}: {{ $turn->status }}@if($turn->refusal_code) ({{ $turn->refusal_code }})@endif — {{ \Illuminate\Support\Str::limit($turn->user_message, 80) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
