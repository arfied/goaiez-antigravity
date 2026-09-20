<div>
    <div class="sends-by-class-view p-4">
        <h2 class="text-lg font-bold text-ink">Sends by class</h2>
        @if($classes->isEmpty())
            <x-ui.empty-state heading="No notification classes yet.">Each kind of send your business makes is classed here as account, marketing or transactional; marketing sends hold during quiet hours.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($classes as $c)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $c->caller_type }}</span>
                        <span class="text-ink-2">{{ $c->classification }}</span>
                        <span class="text-sm text-ink-2">{{ $c->respects_quiet_hours ? 'holds in quiet hours' : 'sends any time' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            @if($success)
                <div class="text-ink font-bold">{{ $success }}</div>
            @endif
            @if($error)
                <div class="text-ink-3 font-bold">{{ $error }}</div>
            @endif
            <form wire:submit="classify" class="flex flex-col gap-2">
                <input type="text" wire:model="callerType" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Caller type">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Classify</button>
            </form>
        </div>
    </div>
</div>
