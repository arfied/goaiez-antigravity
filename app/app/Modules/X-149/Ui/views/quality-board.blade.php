<div>
    <div class="quality-board-view p-4">
        <h3 class="text-lg font-bold">AI Model & Eval Quality Board</h3>
        @if($series->isEmpty())
            <p class="text-gray-500">No quality metrics recorded.</p>
        @else
            <ul>
                @foreach($series as $s)
                    <li>#{{ $s->id }}: Refusal Rate {{ $s->refusal_rate }} (Anomaly: {{ $s->anomaly_detected ? $s->event_name : 'None' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
