<x-surface.sample-state module="C-Billing" screen="dunning_board" />
<div>
    <div class="dunning-board p-4">
        <h3 class="text-lg font-bold">21-Day Dunning Lifecycle Board</h3>
        @if($states->isEmpty())
            <p class="text-gray-500">No active dunning cases.</p>
        @else
            <ul>
                @foreach($states as $s)
                    <li>Day {{ $s->day_in_cycle }}: [{{ $s->status }}] (AI: {{ $s->ai_enabled ? 'ON' : 'OFF' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
