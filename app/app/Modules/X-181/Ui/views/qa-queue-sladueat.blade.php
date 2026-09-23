<div>
    <div class="sla-queue-container p-4 max-w-lg mx-auto md:max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-ink">QA queue</h2>
            <div class="flex items-center space-x-2">
                <x-ui.button wire:click="toggleSample" size="default" variant="secondary">
                    {{ $isSample ? 'Exit Sample' : 'Sample' }}
                </x-ui.button>
            </div>
        </div>

        @if($actionNotice)
            <div class="mb-4 p-2 rounded {{ $noticeType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $actionNotice }}
            </div>
        @endif

        @if($isSample)
            <div class="mb-4 p-2 bg-yellow-100 text-yellow-800 rounded text-sm font-bold">
                SAMPLE DATA
            </div>
        @endif

        @if($isEmpty)
            <x-ui.empty-state heading="The QA queue is clear" action="Show a sample" target="toggleSample">
                A ticket lands here when a review falls below the public threshold and leaves when it is resolved.
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach($tickets as $t)
                    <div class="border rounded p-4 flex flex-col md:flex-row justify-between items-start md:items-center {{ $t->is_breached ? 'bg-red-50' : '' }}">
                        <div>
                            <div class="font-bold">Ticket #{{ $t->id }}</div>
                            <div class="text-sm text-ink-2">{{ $t->subject }}</div>
                            <div class="mt-1">
                                @if($t->is_breached)
                                    <x-ui.status-pill state="alert" label="SLA breached" />
                                @else
                                    <x-ui.status-pill state="ok" label="Due: {{ $t->sla_due_at ? $t->sla_due_at->diffForHumans() : 'N/A' }}" />
                                @endif
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-2">
                            @if($resolvingTicketId === $t->id)
                                <input type="text" wire:model="resolutionNotes" class="border p-1 text-sm rounded" placeholder="Resolution notes...">
                                <x-ui.button wire:click="resolve({{ $t->id }}, $wire.resolutionNotes)" size="default" variant="primary">Submit</x-ui.button>
                                <x-ui.button wire:click="cancelResolve" size="default" variant="quiet">Cancel</x-ui.button>
                            @else
                                <x-ui.button wire:click="startResolve({{ $t->id }})" size="default" variant="primary">Resolve</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <div class="mt-8 mb-6 flex flex-col gap-2 bg-surface p-4 rounded border">
            <h3 class="font-bold text-ink">Create ticket</h3>
            @if ($error)
                <div class="text-red-600 mb-2">{{ $error }}</div>
            @endif
            @if ($success)
                <div class="text-green-600 mb-2">{{ $success }}</div>
            @endif
            <form wire:submit="createTicket" class="flex flex-col gap-2">
                <input type="text" wire:model="ticketSubject" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Subject">
                <input type="text" wire:model="ticketDescription" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Description">
                <button type="submit" class="bg-surface text-ink border rounded p-2 w-32">Create ticket</button>
            </form>
        </div>

        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
