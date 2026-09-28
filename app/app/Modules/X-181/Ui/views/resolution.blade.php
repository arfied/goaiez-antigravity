<div>
    <h2 class="text-lg font-bold text-ink">Resolved tickets</h2>
    @if ($actionNotice)
        <div class="mb-4">
            <x-ui.error-panel heading="Action failed">{{ $actionNotice }}</x-ui.error-panel>
        </div>
    @endif

    @if ($tickets->isEmpty())
        <x-ui.empty-state heading="Nothing resolved yet" action="Show a sample" target="toggleSample">
            A ticket lands here the moment it is resolved from the queue or the ticket page.
        </x-ui.empty-state>
    @else
        <div class="space-y-4">
            @foreach ($tickets as $ticket)
                <div class="p-4 border rounded flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold">{{ $ticket->subject }}</h3>
                        <x-ui.button wire:click="reopen({{ $ticket->id }})">Reopen</x-ui.button>
                    </div>

                    <div class="flex items-center gap-4">
                        @if ($ticket->resolved_at && $ticket->sla_due_at)
                            @if ($ticket->resolved_at <= $ticket->sla_due_at)
                                <x-ui.status-pill state="ok" label="SLA met" />
                            @else
                                <x-ui.status-pill state="alert" label="SLA missed" />
                            @endif
                        @endif

                        @if (in_array($ticket->id, $awaitingCsat, true))
                            <x-ui.status-pill state="unknown" label="Awaiting CSAT" />
                        @endif
                    </div>

                    <div class="text-sm text-ink-2 mt-2">{{ $ticket->resolution_notes }}</div>
                    <div class="text-xs text-ink-2">Resolved: {{ $ticket->resolved_at?->diffForHumans() }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
