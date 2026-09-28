<div>
    <div class="metric-proof-view p-4">
        <h2 class="text-lg font-bold">Published Metric Proof Panel</h2>

        <x-ui.toast kind="error" :message="$error" />
        <x-ui.toast kind="success" :message="$success" />

        <form wire:submit="recordMetric" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <input type="text" wire:model="metricKey" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Metric Key">
            <input type="text" wire:model="publishedValue" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Published Value">
            <textarea wire:model="liveQuery" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Live Query"></textarea>
            <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
        </form>

        @if($verifyError)
            <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $verifyError }}</div>
        @endif
        @if($verifySuccess)
            <div class="bg-surface text-ink border rounded p-4 mb-4">{{ $verifySuccess }}</div>
        @endif

        <form wire:submit="verifyMetric" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <select wire:model="verifyKey" class="border rounded p-2 text-ink flex-1 bg-surface">
                <option value="">Select a metric...</option>
                @foreach($metrics as $m)
                    <option value="{{ $m->metric_key }}">{{ $m->metric_key }}</option>
                @endforeach
            </select>
            <input type="text" wire:model="verifyLiveValue" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Live Value">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Check</button>
        </form>

        @if($metrics->isEmpty())
            <p class="text-ink-2">No public metrics published.</p>
        @else
            <ul>
                @foreach($metrics as $m)
                    <li>{{ $m->metric_key }}: {{ $m->published_value ?? '[PULLED]' }} [{{ $m->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
