<div>
    <h2 class="text-lg font-bold text-ink">Suspension risks</h2>
    @if ($events->isEmpty())
        <x-ui.empty-state heading="No suspension risks on record" action="Show a sample" target="toggleSample">
            A row appears when a post trips the risk ruleset, a post is held because the profile is suspended, or a state read finds the profile suspended.
        </x-ui.empty-state>
    @else
        <div class="space-y-4">
            @foreach ($events as $event)
                <div class="p-4 border rounded flex items-center justify-between gap-4">
                    <div class="flex-1 space-y-2">
                        <div class="flex items-center gap-3">
                            <span class="font-bold">{{ $event->label }}</span>
                            @if ($event->status === 'rejected_risk')
                                <x-ui.status-pill state="alert" label="Risk flagged" />
                            @elseif ($event->status === 'blocked_by_suspension')
                                <x-ui.status-pill state="attention" label="Blocked, profile suspended" />
                            @elseif ($event->status === 'suspended')
                                <x-ui.status-pill state="alert" label="Suspension detected" />
                            @endif
                        </div>
                        <div class="text-ink-2 text-sm">
                            {{ $event->content }}
                        </div>
                        <div class="text-xs text-ink-2">
                            {{ $event->created_at?->diffForHumans() }}
                        </div>
                    </div>
                    
                    <x-ui.button wire:click="pollState({{ $event->connection_id }})">Poll status</x-ui.button>
                </div>
            @endforeach
        </div>
    @endif
</div>
