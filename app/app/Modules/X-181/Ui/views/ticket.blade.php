<div>
    <h2 class="text-lg font-bold text-ink">Ticket</h2>
    @if ($actionNotice)
        <div class="mb-4">
            <x-ui.error-panel heading="Action failed">{{ $actionNotice }}</x-ui.error-panel>
        </div>
    @endif

    @if (! $ticket)
        <x-ui.empty-state heading="Pick a ticket" action="Show a sample" target="toggleSample">
            A ticket opens here from the QA queue when a review falls below the public threshold.
        </x-ui.empty-state>
    @else
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <h3 class="text-lg font-bold">{{ $ticket->subject }}</h3>
                @if ($ticket->status === 'open' || $ticket->status === 'in_progress')
                    <x-ui.status-pill state="attention" label="{{ $ticket->status }}" />
                @elseif ($ticket->status === 'resolved')
                    <x-ui.status-pill state="ok" label="resolved" />
                @else
                    <x-ui.status-pill state="unknown" label="{{ $ticket->status }}" />
                @endif
                
                @if ($ticket->status !== 'resolved' && $ticket->status !== 'closed')
                    @if ($ticket->sla_due_at && $ticket->sla_due_at <= now())
                        <x-ui.status-pill state="alert" label="SLA breached" />
                    @elseif ($ticket->sla_due_at)
                        <x-ui.status-pill state="ok" label="SLA due {{ $ticket->sla_due_at->diffForHumans() }}" />
                    @endif
                @endif
            </div>

            @if ($review && $review->rating)
                <div>Rating: {{ $review->rating }}</div>
            @endif

            <div class="text-ink-2 whitespace-pre-line">{{ $ticket->description }}</div>
            <div class="text-sm text-ink-2">Arrived: {{ $ticket->arrived_at?->diffForHumans() }}</div>

            @if ($ticket->status === 'resolved')
                <div class="mt-4 p-4 border rounded">
                    <div class="font-semibold">Resolution</div>
                    <div class="text-sm text-ink-2 mt-1">{{ $ticket->resolution_notes }}</div>
                    <div class="text-xs text-ink-2 mt-2">Resolved {{ $ticket->resolved_at?->diffForHumans() }}</div>
                </div>
            @else
                <div class="mt-4 flex items-center gap-2">
                    <input type="text" wire:model="resolutionNotes" class="border border-rule p-2 rounded" />
                    <x-ui.button wire:click="resolve($wire.resolutionNotes)">Resolve</x-ui.button>
                </div>
            @endif
        </div>
    @endif
</div>
