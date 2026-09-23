<div>
    <div class="quality-board-view p-4">
        @if($error)
            <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $error }}</div>
        @endif
        @if($success)
            <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $success }}</div>
        @endif

        <form wire:submit="recordQuality" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <input type="text" wire:model="currentRefusalRate" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Current Refusal Rate">
            <input type="text" wire:model="baselineRefusalRate" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Baseline Refusal Rate">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>

        @if($series->isEmpty())
            <p class="text-ink-2">No quality metrics recorded.</p>
        @else
            <ul>
                @foreach($series as $s)
                    <li>#{{ $s->id }}: Refusal Rate {{ $s->refusal_rate }} (Anomaly: {{ $s->anomaly_detected ? $s->event_name : 'None' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
