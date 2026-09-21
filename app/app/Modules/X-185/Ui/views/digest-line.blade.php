<div>
    <div class="digest-line-view p-4">
        <h2 class="text-xl font-bold text-ink">Sequences of messages you have set up</h2>
        
        @if($error) <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $error }}</div> @endif
        @if($success) <div class="text-ink-2 bg-surface border p-2 mb-4 rounded">{{ $success }}</div> @endif
        
        <form wire:submit="createSequence" class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border">
            <input type="text" wire:model="sequenceName" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Sequence name">
            <input type="text" wire:model="firstStepChannel" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="First step channel">
            <input type="text" wire:model="firstStepDelayHours" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Delay in hours">
            <button type="submit" class="bg-surface text-ink border rounded p-2">Set up sequence</button>
        </form>

        @if($sequences->isEmpty())
            <x-ui.empty-state icon="○" heading="No sequences yet">
                When you set up a sequence of messages, it appears here.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($sequences as $s)
                    <li class="text-ink">{{ $s->name }} · {{ $s->is_active ? 'running' : 'stopped' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
